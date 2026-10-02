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
import '../models/team_model.dart';
import '../models/user_model.dart';
import '../services/court_api_service.dart';
import '../services/match_api_service.dart';
import '../services/match_level_api_service.dart';
import '../services/sport_api_service.dart';
import '../services/team_api_service.dart';
import '../services/user_api_service.dart';
import 'match_court_source.dart';
import 'match_schedule_picker.dart';
import 'reserve_court/reserve_courts_screen.dart';
import 'teams/widgets/team_badge.dart';
import 'teams/widgets/team_picker_sheet.dart';

class CreateMatchScreen extends StatefulWidget {
  const CreateMatchScreen({
    required this.matchApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.courtApiService,
    this.teamApiService,
    this.initialBookingCode,
    super.key,
  });

  /// Opened from a reservation ("Crear cancha"): preselects "la reservé en la app" and that booking.
  final String? initialBookingCode;

  final MatchApiService matchApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final CourtApiService courtApiService;

  /// Defaults to one built from [matchApiService]'s URL and token.
  final TeamApiService? teamApiService;

  @override
  State<CreateMatchScreen> createState() => _CreateMatchScreenState();
}

class _CreateMatchScreenState extends State<CreateMatchScreen> {
  final _formKey = GlobalKey<FormState>();
  final _maxPlayersController = TextEditingController();
  final _playerSearchController = TextEditingController();

  List<SportModel> _sports = const [];
  List<MatchLevelModel> _levels = const [];
  int? _sportId;
  int? _levelId;
  CourtModel? _selectedCourt;
  final Set<int> _selectedFieldIds = {};
  bool _courtSearchHasNoResults = false;
  bool _courtSearchFailed = false;
  String _courtSearchText = '';
  MatchGender _gender = MatchGender.mixed;
  bool _loadingOptions = true;
  Object? _optionsError;

  DateTime? _startTime;
  DateTime? _endTime;

  Timer? _debounce;
  List<UserModel> _suggestions = const [];
  bool _searching = false;
  final List<UserModel> _selectedPlayers = [];

  /// Where the court comes from: one of the user's app reservations, booking now, or booked elsewhere.
  MatchCourtSource? _courtSource;
  List<ReservedSlot> _reservedSlots = const [];
  bool _loadingReservedSlots = false;
  String? _reservedSlotsError;
  ReservedSlot? _pickedSlot;

  /// Bumped when a reservation fills the form, so the sport dropdown and schedule picker rebuild.
  int _formRevision = 0;

  /// Teams added to the match (with their roster); all their members become players.
  final List<TeamModel> _selectedTeams = [];
  late final TeamApiService _teamApiService =
      widget.teamApiService ??
      TeamApiService(
        baseUrl: widget.matchApiService.baseUrl,
        token: widget.matchApiService.token,
      );

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
      ]);
      if (!mounted) return;

      final sports = results[0] as List<SportModel>;
      final levels = results[1] as List<MatchLevelModel>;
      setState(() {
        _sports = sports;
        _levels = levels;
        _sportId = sports.isNotEmpty ? sports.first.id : null;
        _levelId = levels.isNotEmpty ? levels.first.id : null;
        _loadingOptions = false;
      });
      final code = widget.initialBookingCode;
      if (code != null && _pickedSlot == null) await _preselectBooking(code);
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

  /// Every player that will be added: the ones picked one by one plus the members of each team.
  /// Keyed by user id so someone in two teams (or also picked by hand) counts once.
  Map<int, ({UserModel user, TeamModel? team})> get _allPlayers {
    final players = <int, ({UserModel user, TeamModel? team})>{};
    for (final team in _selectedTeams) {
      for (final member in team.members) {
        players.putIfAbsent(
          member.user.id,
          () => (user: member.user, team: team),
        );
      }
    }
    for (final user in _selectedPlayers) {
      players.putIfAbsent(user.id, () => (user: user, team: null));
    }
    return players;
  }

  void _changeSport(int sportId) {
    final dropped = _selectedTeams
        .where((team) => team.sport != null && team.sport!.id != sportId)
        .toList();
    setState(() {
      _sportId = sportId;
      _selectedTeams.removeWhere(dropped.contains);
    });
    if (dropped.isNotEmpty) {
      _showMessage(
        dropped.length == 1
            ? 'Quitamos el equipo ${dropped.first.name} porque es de otro deporte.'
            : 'Quitamos ${dropped.length} equipos porque son de otro deporte.',
      );
    }
  }

  Future<void> _addTeam() async {
    final sport = _sports.where((item) => item.id == _sportId).firstOrNull;
    final team = await showModalBottomSheet<TeamModel>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => TeamPickerSheet(
        teamApiService: _teamApiService,
        sportId: _sportId,
        sportName: sport?.name,
        excludedIds: _selectedTeams.map((team) => team.id).toSet(),
      ),
    );
    if (team == null || !mounted) return;
    final before = _allPlayers.length;
    setState(() => _selectedTeams.add(team));
    final added = _allPlayers.length - before;
    _showMessage(
      '${team.name} agregado: $added ${added == 1 ? 'jugador' : 'jugadores'} más.',
    );
  }

  void _removeTeam(TeamModel team) {
    setState(() => _selectedTeams.removeWhere((item) => item.id == team.id));
  }

  void _changeCourtSource(MatchCourtSource source) {
    if (source == _courtSource) return;
    final hadPicked = _pickedSlot != null;
    setState(() {
      _courtSource = source;
      if (hadPicked) _clearReservationAutofill();
    });
    if (source == MatchCourtSource.reservedInApp) _loadReservedSlots();
  }

  /// Venue, courts and time came from a reservation; choosing another option starts clean.
  void _clearReservationAutofill() {
    _pickedSlot = null;
    _selectedCourt = null;
    _selectedFieldIds.clear();
    _startTime = null;
    _endTime = null;
    _formRevision++;
  }

  Future<List<ReservedSlot>> _loadReservedSlots() async {
    setState(() {
      _loadingReservedSlots = true;
      _reservedSlotsError = null;
    });
    try {
      final slots = ReservedSlot.fromReservations(
        await widget.courtApiService.myReservations(),
      );
      if (!mounted) return slots;
      setState(() {
        _reservedSlots = slots;
        _loadingReservedSlots = false;
      });
      return slots;
    } catch (_) {
      if (mounted) {
        setState(() {
          _reservedSlotsError = 'No pudimos cargar tus reservas.';
          _loadingReservedSlots = false;
        });
      }
      return const [];
    }
  }

  /// Fills venue, courts, sport, day and time from the chosen reservation (null clears it).
  Future<void> _pickReservedSlot(ReservedSlot? slot) async {
    if (slot == null) {
      setState(_clearReservationAutofill);
      return;
    }

    // The venue's full court list (with sports) comes from the courts search.
    CourtModel? court;
    try {
      final results = await widget.courtApiService.list(search: slot.venueName);
      court = results.where((item) => item.id == slot.venueId).firstOrNull;
    } catch (_) {
      // Fall back to what the reservation knows.
    }
    court ??= CourtModel(
      id: slot.venueId,
      name: slot.venueName,
      address: slot.address ?? '',
      openingTime: slot.openingTime,
      closingTime: slot.closingTime,
      fields: [
        for (var i = 0; i < slot.fieldIds.length; i++)
          CourtFieldOption(
            id: slot.fieldIds.elementAt(i),
            name: slot.fieldNames.elementAtOrNull(i) ?? 'Cancha',
          ),
      ],
    );
    if (!mounted) return;

    final sport = _sports
        .where((item) => item.name == slot.sportName)
        .firstOrNull;
    setState(() {
      _pickedSlot = slot;
      _selectedCourt = court;
      _selectedFieldIds
        ..clear()
        ..addAll(slot.fieldIds);
      _startTime = slot.start;
      _endTime = slot.end;
      _formRevision++;
    });
    if (sport != null && sport.id != _sportId) _changeSport(sport.id);
  }

  Future<void> _preselectBooking(String code) async {
    setState(() => _courtSource = MatchCourtSource.reservedInApp);
    final slots = await _loadReservedSlots();
    final slot = slots.where((item) => item.bookingCode == code).firstOrNull;
    if (slot != null && mounted) await _pickReservedSlot(slot);
  }

  /// Opens "Reservar cancha"; when a booking is paid it comes back here and fills the form with it.
  Future<void> _bookCourtNow() async {
    Set<String?> knownCodes = const {};
    try {
      knownCodes = (await widget.courtApiService.myReservations())
          .map((item) => item.bookingCode)
          .toSet();
    } catch (_) {
      // Without the previous list we just pick the first upcoming reservation.
    }
    if (!mounted) return;

    final booked = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        settings: const RouteSettings(name: ReserveCourtsScreen.routeName),
        builder: (_) => ReserveCourtsScreen(
          courtApiService: widget.courtApiService,
          sportApiService: widget.sportApiService,
          returnAfterBooking: true,
        ),
      ),
    );
    if (booked != true || !mounted) return;

    setState(() => _courtSource = MatchCourtSource.reservedInApp);
    final slots = await _loadReservedSlots();
    final fresh = slots
        .where((slot) => !knownCodes.contains(slot.bookingCode))
        .firstOrNull;
    if (fresh != null && mounted) {
      await _pickReservedSlot(fresh);
      _showMessage('Reserva lista: completamos la cancha y el horario.');
    }
  }

  Future<bool?> _askJoinAsPlayer() => showCreateMatchJoinDialog(context);

  void _selectCourt(CourtModel court) {
    setState(() {
      _selectedCourt = court;
      _selectedFieldIds.clear();
      _courtSearchHasNoResults = false;
      _courtSearchFailed = false;
    });
  }

  /// Searches the sports centers (`courts` table) on the API by name, address or city.
  /// Debounced: only the text still in the field after a short pause is sent.
  Future<Iterable<CourtModel>> _searchCourts(String text) async {
    _courtSearchText = text;
    await Future<void>.delayed(const Duration(milliseconds: 300));
    if (!mounted || text != _courtSearchText) return const [];

    try {
      final courts = await widget.courtApiService.list(search: text);
      if (!mounted || text != _courtSearchText) return const [];
      setState(() {
        _courtSearchHasNoResults = text.trim().isNotEmpty && courts.isEmpty;
        _courtSearchFailed = false;
      });
      return courts;
    } catch (_) {
      if (mounted && text == _courtSearchText) {
        setState(() {
          _courtSearchHasNoResults = false;
          _courtSearchFailed = true;
        });
      }
      return const [];
    }
  }

  /// Typing after choosing a center drops the choice, so the text always matches the selection.
  void _onCourtSearchChanged(String text) {
    if (_selectedCourt != null && text != _selectedCourt!.name) {
      setState(() {
        _selectedCourt = null;
        _selectedFieldIds.clear();
      });
    }
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
    if (_sportId == null || _levelId == null || _selectedCourt == null) {
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
      _showMessage('Elige el día y el horario del partido.', isError: true);
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
        courtId: _selectedCourt!.id,
        courtFieldIds: _selectedFieldIds.toList(),
        gender: _gender.value,
        startTime: _startTime!,
        endTime: _endTime!,
        maxPlayers: int.parse(_maxPlayersController.text.trim()),
        playerIds: _selectedPlayers.map((u) => u.id).toList(),
        teamIds: _selectedTeams.map((team) => team.id).toList(),
        // Links the match to the booking so "Mis reservas" can open it.
        bookingCode: _pickedSlot?.bookingCode,
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Crear partido')),
      body: _buildBody(context),
    );
  }

  /// Venue search, courts and schedule are filled by hand unless they come from a reservation
  /// (or the user is about to book one).
  bool get _showManualCourt =>
      _courtSource == null || _courtSource == MatchCourtSource.bookedElsewhere;

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
          MatchCourtSourceSection(
            source: _courtSource,
            onSourceChanged: _changeCourtSource,
            slots: _reservedSlots,
            loadingSlots: _loadingReservedSlots,
            slotsError: _reservedSlotsError,
            onRetry: _loadReservedSlots,
            picked: _pickedSlot,
            onPick: _pickReservedSlot,
            onBookNow: _bookCourtNow,
            enabled: !_isSubmitting,
          ),
          const SizedBox(height: 16),
          if (_showManualCourt) ...[
            Autocomplete<CourtModel>(
              displayStringForOption: (court) => court.name,
              optionsBuilder: (TextEditingValue value) =>
                  _searchCourts(value.text),
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
                        key: const Key('court-search-options'),
                        padding: EdgeInsets.zero,
                        shrinkWrap: true,
                        itemCount: optionsList.length,
                        itemBuilder: (context, index) {
                          final court = optionsList[index];
                          return ListTile(
                            title: Text(court.name),
                            subtitle: Text(
                              [court.address, ?court.cityName].join(' · '),
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
                      onChanged: _onCourtSearchChanged,
                      // Search box, not personal data: without this the browser/OS offers the user's saved name.
                      autofillHints: null,
                      enableSuggestions: false,
                      autocorrect: false,
                      // With a center chosen, select its name so typing starts a new search.
                      onTap: () {
                        if (_selectedCourt != null) {
                          controller.selection = TextSelection(
                            baseOffset: 0,
                            extentOffset: controller.text.length,
                          );
                        }
                      },
                      decoration: InputDecoration(
                        labelText: 'Centro / complejo deportivo',
                        hintText: 'Buscar por nombre, dirección o ciudad',
                        helperText: _courtSearchFailed
                            ? 'No pudimos buscar centros deportivos. Intenta de nuevo.'
                            : _courtSearchHasNoResults
                            ? 'No se encontraron centros deportivos.'
                            : null,
                        border: const OutlineInputBorder(),
                        prefixIcon: const Icon(Icons.search),
                      ),
                      validator: (_) => _selectedCourt == null
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
          ],
          DropdownButtonFormField<int>(
            key: ValueKey('sport-$_formRevision-$_sportId'),
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
              if (value != null) _changeSport(value);
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
          if (_showManualCourt) ...[
            MatchSchedulePicker(
              key: ValueKey('schedule-$_formRevision'),
              start: _startTime,
              end: _endTime,
              openingTime: _selectedCourt?.openingTime,
              closingTime: _selectedCourt?.closingTime,
              onChanged: (start, end) => setState(() {
                _startTime = start;
                _endTime = end;
              }),
            ),
            const SizedBox(height: 24),
          ],
          TextFormField(
            controller: _maxPlayersController,
            keyboardType: TextInputType.number,
            // Refreshes the "players vs limit" counter below.
            onChanged: (_) => setState(() {}),
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
          _TeamsSection(
            teams: _selectedTeams,
            enabled: !_isSubmitting && _sportId != null,
            onAdd: _addTeam,
            onRemove: _removeTeam,
          ),
          const SizedBox(height: 24),
          Text(
            'Agregar jugadores',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _playerSearchController,
            autofillHints: null,
            enableSuggestions: false,
            autocorrect: false,
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
          _PlayersSummary(
            players: _allPlayers.values.toList(),
            maxPlayers: int.tryParse(_maxPlayersController.text.trim()),
            onRemove: _removePlayer,
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

/// "Agregar equipos": selected teams with their roster size; adding one adds all its players.
class _TeamsSection extends StatelessWidget {
  const _TeamsSection({
    required this.teams,
    required this.enabled,
    required this.onAdd,
    required this.onRemove,
  });

  final List<TeamModel> teams;
  final bool enabled;
  final VoidCallback onAdd;
  final ValueChanged<TeamModel> onRemove;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Agregar equipos', style: textTheme.titleMedium),
        const SizedBox(height: 2),
        Text(
          'Opcional. Al agregar un equipo se suman todos sus jugadores.',
          style: textTheme.bodySmall,
        ),
        const SizedBox(height: 8),
        for (final team in teams)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: TeamBadge(team: team, size: 40),
              title: Text(team.name),
              subtitle: Text(
                team.members
                    .map((member) => member.user.name.split(' ').first)
                    .join(', '),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              trailing: IconButton(
                tooltip: 'Quitar equipo',
                icon: const Icon(Icons.close),
                onPressed: enabled ? () => onRemove(team) : null,
              ),
            ),
          ),
        OutlinedButton.icon(
          onPressed: enabled ? onAdd : null,
          icon: const Icon(Icons.group_add_outlined),
          label: Text(teams.isEmpty ? 'Agregar equipo' : 'Agregar otro equipo'),
        ),
      ],
    );
  }
}

/// Chips of every player to be added. Team members show the team badge and are removed with
/// their team; players picked one by one can be removed individually.
class _PlayersSummary extends StatelessWidget {
  const _PlayersSummary({
    required this.players,
    required this.maxPlayers,
    required this.onRemove,
  });

  final List<({UserModel user, TeamModel? team})> players;
  final int? maxPlayers;
  final ValueChanged<UserModel> onRemove;

  @override
  Widget build(BuildContext context) {
    if (players.isEmpty) return const SizedBox.shrink();
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final overLimit = maxPlayers != null && players.length > maxPlayers!;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              Icons.groups_outlined,
              size: 18,
              color: overLimit ? colors.error : colors.onSurfaceVariant,
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                maxPlayers == null
                    ? '${players.length} ${players.length == 1 ? 'jugador agregado' : 'jugadores agregados'}'
                    : '${players.length} de $maxPlayers jugadores',
                style: textTheme.bodyMedium?.copyWith(
                  color: overLimit ? colors.error : null,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
        if (overLimit)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              'Superas el límite del partido: súbelo o quita jugadores.',
              style: textTheme.bodySmall?.copyWith(color: colors.error),
            ),
          ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final player in players)
              player.team == null
                  ? InputChip(
                      label: Text(player.user.name),
                      onDeleted: () => onRemove(player.user),
                    )
                  : Tooltip(
                      message:
                          'Del equipo ${player.team!.name}. Quita el equipo para quitarlo.',
                      child: Chip(
                        avatar: TeamBadge(team: player.team!, size: 24),
                        label: Text(player.user.name),
                      ),
                    ),
          ],
        ),
      ],
    );
  }
}
