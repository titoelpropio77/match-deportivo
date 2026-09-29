import 'dart:async';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/match_level_model.dart';
import '../models/match_model.dart';
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
  final Set<int> _selectedFieldIds = {};
  MatchGender _gender = MatchGender.mixed;
  bool _loadingOptions = true;
  Object? _optionsError;

  DateTime? _startTime;
  DateTime? _endTime;

  Timer? _debounce;
  List<UserModel> _suggestions = const [];
  bool _searching = false;
  final List<UserModel> _selectedPlayers = [];

  bool _isSubmitting = false;
  String? _paymentQrPath;
  Uint8List? _paymentQrBytes;
  String? _paymentQrName;

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
          _suggestions = results
              .where((u) => !alreadySelected.contains(u.id))
              .toList();
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

    final result = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    setState(() {
      if (isStart) {
        _startTime = result;
      } else {
        _endTime = result;
      }
    });
  }

  Future<bool?> _askJoinAsPlayer() => showCreateMatchJoinDialog(context);

  CourtModel? get _selectedCourt {
    for (final court in _courts) {
      if (court.id == _courtId) return court;
    }
    return null;
  }

  void _selectCourt(CourtModel court) {
    setState(() {
      _courtId = court.id;
      _selectedFieldIds.clear();
    });
  }

  void _toggleField(int fieldId, bool selected) {
    setState(() {
      if (selected) {
        _selectedFieldIds.add(fieldId);
      } else {
        _selectedFieldIds.remove(fieldId);
      }
    });
  }

  Future<void> _submit() async {
    final isValid = _formKey.currentState?.validate() ?? false;
    if (_sportId == null || _levelId == null || _courtId == null) {
      _showMessage(
        'Selecciona el deporte, el nivel y el centro deportivo.',
        isError: true,
      );
      return;
    }
    if ((_selectedCourt?.fields.isNotEmpty ?? false) &&
        _selectedFieldIds.isEmpty) {
      _showMessage(
        'Selecciona al menos una cancha del centro deportivo.',
        isError: true,
      );
      return;
    }
    if (_startTime == null || _endTime == null) {
      _showMessage('Selecciona la hora de inicio y de término.', isError: true);
      return;
    }
    if (!_endTime!.isAfter(_startTime!)) {
      _showMessage(
        'La hora de término debe ser posterior a la de inicio.',
        isError: true,
      );
      return;
    }
    if (!isValid) return;

    final joinAsPlayer = await _askJoinAsPlayer();
    if (joinAsPlayer == null || !mounted) return;

    setState(() => _isSubmitting = true);

    try {
      await widget.matchApiService.createMatch(
        sportId: _sportId!,
        levelId: _levelId!,
        courtId: _courtId!,
        courtFieldIds: _selectedFieldIds.toList(),
        gender: _gender.value,
        startTime: _startTime!,
        endTime: _endTime!,
        maxPlayers: int.parse(_maxPlayersController.text.trim()),
        playerIds: _selectedPlayers.map((u) => u.id).toList(),
        joinAsPlayer: joinAsPlayer,
        paymentQrPath: _paymentQrPath,
        paymentQrBytes: _paymentQrBytes,
        paymentQrFilename: _paymentQrName,
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

  Future<void> _pickPaymentQr() async {
    try {
      final isMobile =
          !kIsWeb &&
          (defaultTargetPlatform == TargetPlatform.android ||
              defaultTargetPlatform == TargetPlatform.iOS);

      if (isMobile) {
        final picked = await ImagePicker().pickImage(
          source: ImageSource.gallery,
          maxWidth: 1600,
          maxHeight: 1600,
          imageQuality: 90,
        );
        if (picked == null) return;
        final bytes = await picked.readAsBytes();
        if (!mounted) return;
        setState(() {
          _paymentQrPath = picked.path;
          _paymentQrBytes = bytes;
          _paymentQrName = picked.name;
        });
        return;
      }

      final file = await FilePicker.pickFile(
        type: FileType.image,
        dialogTitle: 'Elegir foto del QR de cobro',
      );
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      setState(() {
        _paymentQrPath = file.path;
        _paymentQrBytes = bytes;
        _paymentQrName = file.name;
      });
    } catch (_) {
      if (!mounted) return;
      _showMessage('No pudimos abrir la galería.', isError: true);
    }
  }

  void _clearPaymentQr() {
    setState(() {
      _paymentQrPath = null;
      _paymentQrBytes = null;
      _paymentQrName = null;
    });
  }

  String _formatDateTime(DateTime? value) {
    if (value == null) return 'Seleccionar';
    final date = MaterialLocalizations.of(context).formatMediumDate(value);
    final time = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(value));
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
                    constraints: const BoxConstraints(
                      maxHeight: 240,
                      maxWidth: 400,
                    ),
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
            onSelected: _selectCourt,
            fieldViewBuilder:
                (context, controller, focusNode, onFieldSubmitted) {
                  return TextFormField(
                    controller: controller,
                    focusNode: focusNode,
                    decoration: const InputDecoration(
                      labelText: 'Centro / complejo deportivo',
                      hintText:
                          'Buscar centro deportivo por nombre o dirección',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.search),
                    ),
                    validator: (_) => _courtId == null
                        ? 'Selecciona un centro deportivo.'
                        : null,
                  );
                },
          ),
          if (_selectedCourt != null)
            _CourtFieldsSelector(
              fields: _selectedCourt!.fields,
              selectedIds: _selectedFieldIds,
              enabled: !_isSubmitting,
              onChanged: _toggleField,
            ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            initialValue: _sportId,
            decoration: const InputDecoration(
              labelText: 'Deporte',
              border: OutlineInputBorder(),
            ),
            items: _sports
                .map(
                  (sport) => DropdownMenuItem(
                    value: sport.id,
                    child: Text(sport.name),
                  ),
                )
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
                .map(
                  (level) => DropdownMenuItem(
                    value: level.id,
                    child: Text(level.name),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _levelId = value);
            },
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<MatchGender>(
            initialValue: _gender,
            decoration: const InputDecoration(
              labelText: 'Género',
              border: OutlineInputBorder(),
            ),
            items: MatchGender.values
                .map(
                  (gender) => DropdownMenuItem(
                    value: gender,
                    child: Text(gender.label),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) setState(() => _gender = value);
            },
            validator: (value) =>
                value == null ? 'Selecciona el género.' : null,
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
          Text(
            'Agregar jugadores',
            style: Theme.of(context).textTheme.titleMedium,
          ),
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
                    .map(
                      (user) => ListTile(
                        title: Text(user.name),
                        subtitle: Text(user.email),
                        onTap: () => _addPlayer(user),
                      ),
                    )
                    .toList(),
              ),
            ),
          const SizedBox(height: 12),
          if (_selectedPlayers.isNotEmpty)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _selectedPlayers
                  .map(
                    (user) => Chip(
                      label: Text(user.name),
                      onDeleted: () => _removePlayer(user),
                    ),
                  )
                  .toList(),
            ),
          const SizedBox(height: 24),
          Text('QR de cobro', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(
            'Foto del QR donde vas a cobrar la cancha. La verán los jugadores unidos y tú.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 12),
          _PaymentQrPicker(
            bytes: _paymentQrBytes,
            enabled: !_isSubmitting,
            onPick: _pickPaymentQr,
            onClear: _clearPaymentQr,
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

/// Multi-select of the physical courts of the chosen sports center.
class _CourtFieldsSelector extends StatelessWidget {
  const _CourtFieldsSelector({
    required this.fields,
    required this.selectedIds,
    required this.enabled,
    required this.onChanged,
  });

  final List<CourtFieldOption> fields;
  final Set<int> selectedIds;
  final bool enabled;
  final void Function(int fieldId, bool selected) onChanged;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Canchas', style: textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(
            fields.isEmpty
                ? 'Este centro deportivo no tiene canchas registradas.'
                : 'Elige una o varias canchas donde se jugará el partido.',
            style: textTheme.bodySmall,
          ),
          if (fields.isNotEmpty) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: fields
                  .map(
                    (field) => FilterChip(
                      label: Text(field.label),
                      selected: selectedIds.contains(field.id),
                      onSelected: enabled
                          ? (selected) => onChanged(field.id, selected)
                          : null,
                    ),
                  )
                  .toList(),
            ),
          ],
        ],
      ),
    );
  }
}

class _PaymentQrPicker extends StatelessWidget {
  const _PaymentQrPicker({
    required this.bytes,
    required this.enabled,
    required this.onPick,
    required this.onClear,
  });

  final Uint8List? bytes;
  final bool enabled;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final hasPhoto = bytes != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        InkWell(
          onTap: enabled ? onPick : null,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 180,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: colors.outline),
              color: colors.surfaceContainerHighest,
            ),
            clipBehavior: Clip.antiAlias,
            child: hasPhoto
                ? Image.memory(bytes!, fit: BoxFit.contain)
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.qr_code_2,
                        size: 40,
                        color: colors.onSurfaceVariant,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Subir foto del QR',
                        style: TextStyle(color: colors.onSurfaceVariant),
                      ),
                    ],
                  ),
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            TextButton.icon(
              onPressed: enabled ? onPick : null,
              icon: const Icon(Icons.photo_library_outlined, size: 18),
              label: Text(hasPhoto ? 'Cambiar foto' : 'Elegir de la galería'),
            ),
            if (hasPhoto)
              TextButton(
                onPressed: enabled ? onClear : null,
                child: const Text('Quitar foto'),
              ),
          ],
        ),
      ],
    );
  }
}

/// Asks whether the organizer should also join as a player when creating a match.
///
/// Returns `true` to create and join, `false` to create only, or `null` if cancelled.
Future<bool?> showCreateMatchJoinDialog(BuildContext context) {
  return showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('¿Quieres unirte al partido?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Crear el partido no te inscribe automáticamente. Elige cómo continuar.',
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Crear y añadirme como jugador'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Solo crear el partido'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancelar'),
          ),
        ],
      ),
    ),
  );
}
