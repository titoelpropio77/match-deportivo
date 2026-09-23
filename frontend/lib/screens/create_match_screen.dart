import 'dart:async';

import 'package:flutter/material.dart';

import '../models/match_level_model.dart';
import '../models/sport_model.dart';
import '../models/court_model.dart';
import '../models/user_model.dart';
import '../services/court_api_service.dart';
import '../services/match_api_service.dart';
import '../services/match_level_api_service.dart';
import '../services/sport_api_service.dart';
import '../services/user_api_service.dart';

class CreateMatchScreen extends StatefulWidget {
  const CreateMatchScreen({
    required this.matchApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.courtApiService,
    super.key,
  });

  final MatchApiService matchApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final CourtApiService courtApiService;

  @override
  State<CreateMatchScreen> createState() => _CreateMatchScreenState();
}

class _CreateMatchScreenState extends State<CreateMatchScreen> {
  final _formKey = GlobalKey<FormState>();
  final _maxPlayersController = TextEditingController();
  final _playerSearchController = TextEditingController();

  List<SportModel> _sports = const [];
  List<MatchLevelModel> _levels = const [];
  List<CourtModel> _courts = const [];
  int? _sportId;
  int? _levelId;
  int? _courtId;
  bool _loadingOptions = true;
  Object? _optionsError;

  DateTime? _startTime;
  DateTime? _endTime;

  Timer? _debounce;
  List<UserModel> _suggestions = const [];
  bool _searching = false;
  final List<UserModel> _selectedPlayers = [];

  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loadingOptions = true;
      _optionsError = null;
    });

    try {
      final results = await Future.wait([
        widget.sportApiService.list(),
        widget.matchLevelApiService.list(),
        widget.courtApiService.list(),
      ]);
      if (!mounted) return;

      final sports = results[0] as List<SportModel>;
      final levels = results[1] as List<MatchLevelModel>;
      final courts = results[2] as List<CourtModel>;
      setState(() {
        _sports = sports;
        _levels = levels;
        _courts = courts;
        _sportId = sports.isNotEmpty ? sports.first.id : null;
        _levelId = levels.isNotEmpty ? levels.first.id : null;
        _courtId = courts.isNotEmpty ? courts.first.id : null;
        _loadingOptions = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _optionsError = error;
        _loadingOptions = false;
      });
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
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
    if (_sportId == null || _levelId == null || _courtId == null) {
      _showMessage('Selecciona el deporte, el nivel y la cancha.', isError: true);
      return;
    }
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
        sportId: _sportId!,
        levelId: _levelId!,
        courtId: _courtId!,
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
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loadingOptions) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_optionsError != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_outlined, size: 48),
              const SizedBox(height: 12),
              const Text(
                'No pudimos cargar los deportes, niveles y canchas.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _loadOptions,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      );
    }

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Autocomplete<CourtModel>(
            initialValue: TextEditingValue(
              text: _courts.isNotEmpty ? _courts.first.name : '',
            ),
            displayStringForOption: (court) => court.name,
            optionsBuilder: (TextEditingValue value) {
              final query = value.text.trim().toLowerCase();
              if (query.isEmpty) return _courts;
              return _courts.where(
                (court) =>
                    court.name.toLowerCase().contains(query) ||
                    court.address.toLowerCase().contains(query),
              );
            },
            optionsViewBuilder: (context, onSelected, options) {
              final optionsList = options.toList();
              return Align(
                alignment: Alignment.topLeft,
                child: Material(
                  elevation: 4,
                  borderRadius: BorderRadius.circular(12),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 240, maxWidth: 400),
                    child: ListView.builder(
                      padding: EdgeInsets.zero,
                      shrinkWrap: true,
                      itemCount: optionsList.length,
                      itemBuilder: (context, index) {
                        final court = optionsList[index];
                        return ListTile(
                          title: Text(court.name),
                          subtitle: Text(
                            court.address,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          onTap: () => onSelected(court),
                        );
                      },
                    ),
                  ),
                ),
              );
            },
            onSelected: (court) => setState(() => _courtId = court.id),
            fieldViewBuilder: (context, controller, focusNode, onFieldSubmitted) {
              return TextFormField(
                controller: controller,
                focusNode: focusNode,
                decoration: const InputDecoration(
                  labelText: 'Lugar / Cancha',
                  hintText: 'Buscar cancha por nombre o dirección',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.search),
                ),
                validator: (_) =>
                    _courtId == null ? 'Selecciona una cancha.' : null,
              );
            },
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            initialValue: _sportId,
            decoration: const InputDecoration(
              labelText: 'Deporte',
              border: OutlineInputBorder(),
            ),
            items: _sports
                .map((sport) => DropdownMenuItem(
                      value: sport.id,
                      child: Text(sport.name),
                    ))
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _sportId = value);
            },
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            initialValue: _levelId,
            decoration: const InputDecoration(
              labelText: 'Nivel',
              border: OutlineInputBorder(),
            ),
            items: _levels
                .map((level) => DropdownMenuItem(
                      value: level.id,
                      child: Text(level.name),
                    ))
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _levelId = value);
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
    );
  }
}
