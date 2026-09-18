import 'dart:async';

import 'package:flutter/material.dart';

import '../models/match_model.dart';
import '../models/user_model.dart';
import '../services/match_api_service.dart';
import '../services/user_api_service.dart';

class CreateMatchScreen extends StatefulWidget {
  const CreateMatchScreen({
    required this.matchApiService,
    required this.userApiService,
    super.key,
  });

  final MatchApiService matchApiService;
  final UserApiService userApiService;

  @override
  State<CreateMatchScreen> createState() => _CreateMatchScreenState();
}

class _CreateMatchScreenState extends State<CreateMatchScreen> {
  final _formKey = GlobalKey<FormState>();
  final _locationController = TextEditingController();
  final _maxPlayersController = TextEditingController();
  final _playerSearchController = TextEditingController();

  MatchSport _sport = MatchSport.football5;
  MatchLevel _level = MatchLevel.basico;
  DateTime? _startTime;
  DateTime? _endTime;

  Timer? _debounce;
  List<UserModel> _suggestions = const [];
  bool _searching = false;
  final List<UserModel> _selectedPlayers = [];

  bool _isSubmitting = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _locationController.dispose();
    _maxPlayersController.dispose();
    _playerSearchController.dispose();
    super.dispose();
  }

  void _onPlayerSearchChanged(String query) {
    _debounce?.cancel();
    if (query.trim().isEmpty) {
      setState(() => _suggestions = const []);
      return;
    }

    _debounce = Timer(const Duration(milliseconds: 400), () async {
      setState(() => _searching = true);
      try {
        final results = await widget.userApiService.search(query);
        if (!mounted) return;

        final alreadySelected = _selectedPlayers.map((u) => u.id).toSet();
        setState(() {
          _suggestions =
              results.where((u) => !alreadySelected.contains(u.id)).toList();
          _searching = false;
        });
      } catch (_) {
        if (!mounted) return;
        setState(() {
          _suggestions = const [];
          _searching = false;
        });
      }
    });
  }

  void _addPlayer(UserModel user) {
    setState(() {
      _selectedPlayers.add(user);
      _suggestions = const [];
      _playerSearchController.clear();
    });
  }

  void _removePlayer(UserModel user) {
    setState(() => _selectedPlayers.removeWhere((u) => u.id == user.id));
  }

  Future<void> _pickDateTime({required bool isStart}) async {
    final initial = (isStart ? _startTime : _endTime) ?? DateTime.now();

    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) return;

    final result = DateTime(date.year, date.month, date.day, time.hour, time.minute);
    setState(() {
      if (isStart) {
        _startTime = result;
      } else {
        _endTime = result;
      }
    });
  }

  Future<void> _submit() async {
    final isValid = _formKey.currentState?.validate() ?? false;
    if (_startTime == null || _endTime == null) {
      _showMessage('Selecciona la hora de inicio y de término.', isError: true);
      return;
    }
    if (!_endTime!.isAfter(_startTime!)) {
      _showMessage('La hora de término debe ser posterior a la de inicio.', isError: true);
      return;
    }
    if (!isValid) return;

    setState(() => _isSubmitting = true);

    try {
      await widget.matchApiService.createMatch(
        sport: _sport,
        level: _level,
        location: _locationController.text.trim(),
        startTime: _startTime!,
        endTime: _endTime!,
        maxPlayers: int.parse(_maxPlayersController.text.trim()),
        playerIds: _selectedPlayers.map((u) => u.id).toList(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError
              ? Theme.of(context).colorScheme.error
              : Theme.of(context).colorScheme.inverseSurface,
        ),
      );
  }

  String _errorMessage(Object error) {
    if (error is MatchApiException) return error.message;
    return 'No pudimos crear el partido.';
  }

  String _levelLabel(MatchLevel level) {
    switch (level) {
      case MatchLevel.basico:
        return 'Básico';
      case MatchLevel.basicoIntermedio:
        return 'Básico/Intermedio';
      case MatchLevel.intermedio:
        return 'Intermedio';
      case MatchLevel.intermedioAvanzado:
        return 'Intermedio Avanzado';
      case MatchLevel.avanzado:
        return 'Avanzado';
      case MatchLevel.elite:
        return 'Élite';
    }
  }

  String _sportLabel(MatchSport sport) {
    switch (sport) {
      case MatchSport.football5:
        return 'Fútbol 5';
      case MatchSport.football7:
        return 'Fútbol 7';
      case MatchSport.padel:
        return 'Pádel';
    }
  }

  String _formatDateTime(DateTime? value) {
    if (value == null) return 'Seleccionar';
    final date = MaterialLocalizations.of(context).formatMediumDate(value);
    final time =
        MaterialLocalizations.of(context).formatTimeOfDay(TimeOfDay.fromDateTime(value));
    return '$date · $time';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Crear partido')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _locationController,
              decoration: const InputDecoration(
                labelText: 'Lugar / Cancha',
                border: OutlineInputBorder(),
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? 'Ingresa el lugar o cancha.'
                  : null,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<MatchSport>(
              initialValue: _sport,
              decoration: const InputDecoration(
                labelText: 'Deporte',
                border: OutlineInputBorder(),
              ),
              items: MatchSport.values
                  .map((sport) => DropdownMenuItem(
                        value: sport,
                        child: Text(_sportLabel(sport)),
                      ))
                  .toList(),
              onChanged: (value) {
                if (value != null) setState(() => _sport = value);
              },
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<MatchLevel>(
              initialValue: _level,
              decoration: const InputDecoration(
                labelText: 'Nivel',
                border: OutlineInputBorder(),
              ),
              items: MatchLevel.values
                  .map((level) => DropdownMenuItem(
                        value: level,
                        child: Text(_levelLabel(level)),
                      ))
                  .toList(),
              onChanged: (value) {
                if (value != null) setState(() => _level = value);
              },
            ),
            const SizedBox(height: 16),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Hora de inicio'),
              subtitle: Text(_formatDateTime(_startTime)),
              trailing: const Icon(Icons.calendar_today_outlined),
              onTap: () => _pickDateTime(isStart: true),
            ),
            const Divider(height: 1),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Hora de término'),
              subtitle: Text(_formatDateTime(_endTime)),
              trailing: const Icon(Icons.calendar_today_outlined),
              onTap: () => _pickDateTime(isStart: false),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _maxPlayersController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Límite de jugadores/equipos',
                border: OutlineInputBorder(),
              ),
              validator: (value) {
                final parsed = int.tryParse(value?.trim() ?? '');
                if (parsed == null || parsed < 1) {
                  return 'Ingresa un número válido mayor a 0.';
                }
                return null;
              },
            ),
            const SizedBox(height: 24),
            Text('Agregar jugadores', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            TextField(
              controller: _playerSearchController,
              decoration: InputDecoration(
                labelText: 'Buscar por nombre o email',
                border: const OutlineInputBorder(),
                suffixIcon: _searching
                    ? const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : null,
              ),
              onChanged: _onPlayerSearchChanged,
            ),
            if (_suggestions.isNotEmpty)
              Card(
                margin: const EdgeInsets.only(top: 4),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: _suggestions
                      .map((user) => ListTile(
                            title: Text(user.name),
                            subtitle: Text(user.email),
                            onTap: () => _addPlayer(user),
                          ))
                      .toList(),
                ),
              ),
            const SizedBox(height: 12),
            if (_selectedPlayers.isNotEmpty)
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _selectedPlayers
                    .map((user) => Chip(
                          label: Text(user.name),
                          onDeleted: () => _removePlayer(user),
                        ))
                    .toList(),
              ),
            const SizedBox(height: 32),
            FilledButton(
              onPressed: _isSubmitting ? null : _submit,
              child: _isSubmitting
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Crear partido'),
            ),
          ],
        ),
      ),
    );
  }
}
