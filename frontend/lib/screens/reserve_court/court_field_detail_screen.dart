import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/sport_model.dart';
import '../../services/court_api_service.dart';
import 'court_payment_screen.dart';
import 'reserve_courts_screen.dart';

class CourtFieldDetailScreen extends StatefulWidget {
  const CourtFieldDetailScreen({
    required this.field,
    required this.initialDate,
    required this.courtApiService,
    super.key,
  });

  final CourtFieldModel field;
  final DateTime initialDate;
  final CourtApiService courtApiService;

  @override
  State<CourtFieldDetailScreen> createState() => _CourtFieldDetailScreenState();
}

class _CourtFieldDetailScreenState extends State<CourtFieldDetailScreen> {
  late DateTime _date = DateUtils.dateOnly(widget.initialDate);
  late CourtFieldSummaryModel _field = CourtFieldSummaryModel(
    id: widget.field.id,
    name: widget.field.name,
    pricePerHour: widget.field.pricePerHour,
    sports: widget.field.sports,
  );
  late List<CourtFieldSummaryModel> _venueFields = [_field];
  late SportModel? _sport = widget.field.sports.isEmpty ? null : widget.field.sports.first;
  CourtAvailabilityModel? _availability;
  bool _loading = true;
  String? _error;
  String? _selectedStart;
  int _hours = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _selectedStart = null;
      _hours = 1;
    });
    try {
      final availability = await widget.courtApiService.availability(
        fieldId: _field.id,
        date: _date,
      );
      if (!mounted) return;
      setState(() {
        _availability = availability;
        if (availability.venueFields.isNotEmpty) {
          _venueFields = availability.venueFields;
        }
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is CourtApiException
            ? error.message
            : 'No pudimos cargar los horarios.';
        _loading = false;
      });
    }
  }

  /// Every sport offered by any court of the sports center.
  List<SportModel> get _venueSports {
    final byId = <int, SportModel>{};
    for (final field in _venueFields) {
      for (final sport in field.sports) {
        byId.putIfAbsent(sport.id, () => sport);
      }
    }
    return byId.values.toList();
  }

  List<CourtFieldSummaryModel> get _fieldsForSport {
    final sport = _sport;
    if (sport == null) return _venueFields;
    return _venueFields.where((field) => field.sports.any((item) => item.id == sport.id)).toList();
  }

  void _selectSport(SportModel sport) {
    setState(() => _sport = sport);
    if (_field.sports.any((item) => item.id == sport.id)) return;
    final next = _fieldsForSport;
    if (next.isNotEmpty) _selectField(next.first);
  }

  void _selectField(CourtFieldSummaryModel field) {
    if (field.id == _field.id) return;
    setState(() => _field = field);
    _load();
  }

  bool _canBookHours(int hours) {
    final start = _selectedStart;
    final slots = _availability?.slots;
    if (start == null || slots == null) return false;
    final index = slots.indexWhere((slot) => slot.start == start);
    if (index < 0) return false;
    for (var offset = 0; offset < hours; offset++) {
      if (index + offset >= slots.length || !slots[index + offset].available) {
        return false;
      }
    }
    return true;
  }

  double get _amount => _field.pricePerHour * _hours;

  String? get _endTime {
    if (!_canBookHours(_hours) || _selectedStart == null) return null;
    final parts = _selectedStart!.split(':');
    final start = DateTime(2026, 1, 1, int.parse(parts[0]), int.parse(parts[1]));
    final end = start.add(Duration(hours: _hours));
    final hour = end.hour.toString().padLeft(2, '0');
    final minute = end.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  }

  Future<void> _openPayment() async {
    final sport = _sport;
    if (sport == null) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CourtPaymentScreen(
          field: CourtFieldModel(
            id: _field.id,
            name: _field.name,
            pricePerHour: _field.pricePerHour,
            venue: widget.field.venue,
            sports: _field.sports,
          ),
          sport: sport,
          date: _date,
          startTime: _selectedStart!,
          endTime: _endTime!,
          hours: _hours,
          courtApiService: widget.courtApiService,
        ),
      ),
    );
    // The hour may have been taken or released meanwhile.
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final venue = widget.field.venue;
    final textTheme = Theme.of(context).textTheme;
    final canPay = _endTime != null && _sport != null;
    return Scaffold(
      appBar: AppBar(title: Text(venue.name)),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton(
            onPressed: canPay ? _openPayment : null,
            child: Text(
              canPay ? 'Reservar · ${formatBs(_amount)}' : 'Selecciona un horario',
            ),
          ),
        ),
      ),
      body: ListView(
        children: [
          if (venue.photos.isNotEmpty)
            SizedBox(
              height: 200,
              child: PageView(
                children: [
                  for (final photo in venue.photos)
                    Image.network(
                      photo,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => const ColoredBox(
                        color: Colors.black12,
                        child: Center(child: Icon(Icons.image_not_supported_outlined)),
                      ),
                    ),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(venue.name, style: textTheme.headlineSmall),
                Text(venue.address),
                if (venue.openingTime != null && venue.closingTime != null)
                  Text(
                    'Atiende ${_hhmm(venue.openingTime!)}–${_hhmm(venue.closingTime!)}',
                    style: textTheme.bodySmall,
                  ),
                const SizedBox(height: 16),
                Text('Deporte', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final sport in _venueSports)
                      ChoiceChip(
                        label: Text(sport.name),
                        selected: _sport?.id == sport.id,
                        onSelected: (_) => _selectSport(sport),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                Text('Cancha', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final field in _fieldsForSport)
                      ChoiceChip(
                        label: Text('${field.name} · ${formatBs(field.pricePerHour)}/h'),
                        selected: _field.id == field.id,
                        onSelected: (_) => _selectField(field),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                Text('Fecha', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                SizedBox(
                  height: 48,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: 14,
                    separatorBuilder: (_, _) => const SizedBox(width: 8),
                    itemBuilder: (context, index) {
                      final day = DateUtils.dateOnly(DateTime.now()).add(Duration(days: index));
                      final selected = DateUtils.isSameDay(day, _date);
                      return ChoiceChip(
                        label: Text(_dayLabel(day)),
                        selected: selected,
                        onSelected: (_) {
                          setState(() => _date = day);
                          _load();
                        },
                      );
                    },
                  ),
                ),
                const SizedBox(height: 16),
                if (_loading)
                  const Center(child: CircularProgressIndicator())
                else if (_error != null)
                  Column(
                    children: [
                      Text(_error!),
                      TextButton(onPressed: _load, child: const Text('Reintentar')),
                    ],
                  )
                else
                  _Schedule(
                    availability: _availability!,
                    selectedStart: _selectedStart,
                    onSelect: (start) {
                      setState(() {
                        _selectedStart = start;
                        if (!_canBookHours(_hours)) _hours = 1;
                      });
                    },
                  ),
                const SizedBox(height: 16),
                Text('Horas de reserva', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                SegmentedButton<int>(
                  segments: const [
                    ButtonSegment(value: 1, label: Text('1 hora')),
                    ButtonSegment(value: 2, label: Text('2 horas')),
                  ],
                  selected: {_hours},
                  onSelectionChanged: (selection) {
                    final hours = selection.first;
                    if (!_canBookHours(hours) && _selectedStart != null) {
                      ScaffoldMessenger.of(context)
                        ..hideCurrentSnackBar()
                        ..showSnackBar(
                          const SnackBar(content: Text('No hay espacio para esas horas.')),
                        );
                      return;
                    }
                    setState(() => _hours = hours);
                  },
                ),
                if (_endTime != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    '${_sport?.name ?? ''} · $_selectedStart – $_endTime · ${formatBs(_amount)}',
                    style: textTheme.titleMedium,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Schedule extends StatelessWidget {
  const _Schedule({
    required this.availability,
    required this.selectedStart,
    required this.onSelect,
  });

  final CourtAvailabilityModel availability;
  final String? selectedStart;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final ranges = availability.freeRanges;
    final reserved = availability.slots.where((slot) => slot.isReserved).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Horarios', style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: 8),
        if (ranges.isEmpty)
          const Text('No hay horarios libres este día.')
        else
          Text(
            'Libre: ${ranges.map((range) => '${range.start}–${range.end}').join(', ')}',
          ),
        if (reserved.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            'Reservado: ${reserved.map((slot) => '${slot.start}–${slot.end}${slot.sportName == null ? '' : ' (${slot.sportName})'}').join(', ')}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
        const SizedBox(height: 12),
        const _Legend(),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            const spacing = 8.0;
            final columns = constraints.maxWidth >= 480 ? 4 : 3;
            final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;
            return Wrap(
              spacing: spacing,
              runSpacing: spacing,
              children: [
                for (final slot in availability.slots)
                  SizedBox(
                    width: width,
                    child: _SlotTile(
                      slot: slot,
                      selected: selectedStart == slot.start,
                      onTap: () {
                        if (slot.available) {
                          onSelect(slot.start);
                          return;
                        }
                        final message = slot.isReserved
                            ? 'Ese horario ya está reservado${slot.sportName == null ? '' : ' para ${slot.sportName}'}.'
                            : 'Ese horario ya pasó.';
                        ScaffoldMessenger.of(context)
                          ..hideCurrentSnackBar()
                          ..showSnackBar(SnackBar(content: Text(message)));
                      },
                    ),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _SlotTile extends StatelessWidget {
  const _SlotTile({required this.slot, required this.selected, required this.onTap});

  final CourtSlotModel slot;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final (background, foreground, border) = switch (slot) {
      _ when selected => (colors.primary, colors.onPrimary, colors.primary),
      _ when slot.isReserved => (colors.errorContainer, colors.onErrorContainer, colors.errorContainer),
      _ when !slot.available => (
          colors.surfaceContainerHighest,
          colors.onSurface.withValues(alpha: 0.38),
          colors.surfaceContainerHighest,
        ),
      _ => (colors.surface, colors.onSurface, colors.outline),
    };

    return Material(
      color: background,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: border),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${slot.start}–${slot.end}',
                style: TextStyle(color: foreground, fontWeight: FontWeight.w600),
              ),
              Text(
                switch (slot) {
                  _ when slot.isReserved => slot.sportName ?? 'Reservado',
                  _ when slot.isPast => 'Pasado',
                  _ => 'Libre',
                },
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: foreground, fontSize: 12),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    Widget item(Color color, String label, {Color? border}) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 14,
              height: 14,
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(4),
                border: Border.all(color: border ?? color),
              ),
            ),
            const SizedBox(width: 6),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        );

    return Wrap(
      spacing: 16,
      runSpacing: 4,
      children: [
        item(colors.surface, 'Libre', border: colors.outline),
        item(colors.errorContainer, 'Reservado'),
        item(colors.primary, 'Tu selección'),
      ],
    );
  }
}

String _dayLabel(DateTime date) {
  const days = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
  return '${days[date.weekday - 1]} ${date.day}';
}

String _hhmm(String time) => time.length >= 5 ? time.substring(0, 5) : time;
