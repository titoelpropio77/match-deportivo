import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/sport_model.dart';
import '../../services/court_api_service.dart';
import '../../services/event_space_api_service.dart';
import '../event_spaces/event_spaces_teaser.dart';
import 'court_payment_screen.dart';
import 'reserve_courts_screen.dart';

class CourtFieldDetailScreen extends StatefulWidget {
  const CourtFieldDetailScreen({
    required this.field,
    required this.initialDate,
    required this.courtApiService,
    this.initialSport,
    this.returnAfterBooking = false,
    this.eventSpaceApiService,
    super.key,
  });

  /// Loads the center's event spaces; built from [courtApiService] when null.
  final EventSpaceApiService? eventSpaceApiService;

  /// See [ReserveCourtsScreen.returnAfterBooking].
  final bool returnAfterBooking;

  final CourtFieldModel field;

  /// Sport to preselect (e.g. the one filtered in the list); defaults to the court's first sport.
  final SportModel? initialSport;
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
    airConditioningPrice: widget.field.airConditioningPrice,
    lightingPrice: widget.field.lightingPrice,
    lightingFrom: widget.field.lightingFrom,
  );
  late List<CourtFieldSummaryModel> _venueFields = [_field];
  late SportModel? _sport =
      widget.initialSport ?? (widget.field.sports.isEmpty ? null : widget.field.sports.first);
  CourtAvailabilityModel? _availability;
  bool _loading = true;
  String? _error;

  late final EventSpaceApiService _eventSpaceApiService = widget.eventSpaceApiService ??
      EventSpaceApiService(baseUrl: widget.courtApiService.baseUrl, token: widget.courtApiService.token);

  /// Picked hours across courts and days, keyed by [SelectedSlot.key]. Kept while browsing.
  final Map<String, SelectedSlot> _selected = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
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
        // Drop picked hours of this court/day that someone else booked meanwhile.
        for (final slot in availability.slots.where((slot) => !slot.available)) {
          _selected.remove(SelectedSlot.slotKey(_field.id, _date, slot.start));
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

  void _toggleSlot(CourtSlotModel slot) {
    final sport = _sport;
    if (sport == null) return;
    final key = SelectedSlot.slotKey(_field.id, _date, slot.start);
    setState(() {
      if (_selected.remove(key) == null) {
        _selected[key] = SelectedSlot(field: _field, sport: sport, date: _date, start: slot.start);
      }
    });
  }

  void _removeItem(BookingItem item) {
    final start = int.parse(item.startTime.split(':')[0]);
    setState(() {
      for (var hour = start; hour < start + item.hours; hour++) {
        _selected.remove(SelectedSlot.slotKey(item.field.id, item.date, '${hour.toString().padLeft(2, '0')}:00'));
      }
    });
  }

  int _selectedCountFor(int fieldId) => _selected.values.where((slot) => slot.field.id == fieldId).length;

  List<BookingItem> get _items => groupSlotsIntoRanges(_selected.values);

  double get _amount => _items.fold(0, (total, item) => total + item.amount);

  Future<void> _openPayment() async {
    final booked = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CourtPaymentScreen(
          venue: widget.field.venue,
          items: _items,
          courtApiService: widget.courtApiService,
          returnAfterBooking: widget.returnAfterBooking,
        ),
      ),
    );
    if (!mounted) return;
    if (booked == true) setState(_selected.clear);
    // The hours may have been taken or released meanwhile.
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final venue = widget.field.venue;
    final textTheme = Theme.of(context).textTheme;
    final items = _items;
    final hours = items.fold<int>(0, (total, item) => total + item.hours);
    return Scaffold(
      appBar: AppBar(title: Text(venue.name)),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton(
            onPressed: items.isEmpty ? null : _openPayment,
            child: Text(
              items.isEmpty
                  ? 'Selecciona uno o más horarios'
                  : 'Continuar · $hours ${hours == 1 ? 'hora' : 'horas'} · ${formatBs(_amount)}',
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
                        label: Text(
                          '${field.name} · ${formatBs(field.pricePerHour)}/h'
                          '${_selectedCountFor(field.id) > 0 ? ' · ${_selectedCountFor(field.id)} h' : ''}',
                        ),
                        selected: _field.id == field.id,
                        onSelected: (_) => _selectField(field),
                      ),
                  ],
                ),
                _FieldExtras(field: _field),
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
                    isSelected: (slot) => _selected.containsKey(SelectedSlot.slotKey(_field.id, _date, slot.start)),
                    onToggle: _toggleSlot,
                  ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  _SelectionSummary(items: items, total: _amount, onRemove: _removeItem),
                ],
                // The court list tells whether the center rents event spaces; skip the request when it doesn't.
                if (venue.eventSpacesCount != 0)
                  EventSpacesTeaser(
                    courtId: venue.id,
                    eventSpaceApiService: _eventSpaceApiService,
                  ),
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
    required this.isSelected,
    required this.onToggle,
  });

  final CourtAvailabilityModel availability;
  final bool Function(CourtSlotModel slot) isSelected;
  final ValueChanged<CourtSlotModel> onToggle;

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
        const SizedBox(height: 4),
        Text(
          'Toca varias horas seguidas para jugar más de una hora. Puedes sumar otras canchas o '
          'fechas: tu selección se mantiene.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
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
                      selected: isSelected(slot),
                      onTap: () {
                        if (slot.available) {
                          onToggle(slot);
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
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Flexible(
                    child: Text(
                      '${slot.start}–${slot.end}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: foreground, fontWeight: FontWeight.w600),
                    ),
                  ),
                  if (slot.lighting && slot.available) ...[
                    const SizedBox(width: 2),
                    Tooltip(
                      message: 'Con luz: tiene costo extra',
                      child: Icon(Icons.lightbulb_outline, size: 14, color: foreground),
                    ),
                  ],
                ],
              ),
              Text(
                switch (slot) {
                  _ when selected => 'Elegido',
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

/// "Luz desde las 18:00: + Bs 10/h" and "Aire acondicionado opcional: + Bs 15/h" of the selected court.
class _FieldExtras extends StatelessWidget {
  const _FieldExtras({required this.field});

  final CourtFieldSummaryModel field;

  @override
  Widget build(BuildContext context) {
    final lines = [
      if ((field.lightingPrice ?? 0) > 0 && field.lightingFrom != null)
        (Icons.lightbulb_outline, 'Luz desde las ${field.lightingFrom}: + ${formatBs(field.lightingPrice!)}/h'),
      if (field.offersAirConditioning)
        (Icons.ac_unit, 'Aire acondicionado opcional: + ${formatBs(field.airConditioningPrice!)}/h'),
    ];
    if (lines.isEmpty) return const SizedBox.shrink();

    final style = Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        );
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final (icon, text) in lines)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Row(
                children: [
                  Icon(icon, size: 14, color: style?.color),
                  const SizedBox(width: 4),
                  Expanded(child: Text(text, style: style)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Picked ranges, one row per court/day/consecutive hours, with their price.
class _SelectionSummary extends StatelessWidget {
  const _SelectionSummary({required this.items, required this.total, required this.onRemove});

  final List<BookingItem> items;
  final double total;
  final ValueChanged<BookingItem> onRemove;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Tu selección', style: textTheme.titleSmall),
            for (final item in items)
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${item.field.name} · ${item.startTime}–${item.endTime}',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        Text(
                          '${_dayLabel(item.date)} · ${item.sport.name} · '
                          '${item.hours} ${item.hours == 1 ? 'hora' : 'horas'}',
                          style: textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  Text(formatBs(item.amount)),
                  IconButton(
                    tooltip: 'Quitar',
                    icon: const Icon(Icons.close),
                    onPressed: () => onRemove(item),
                  ),
                ],
              ),
            const Divider(),
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Row(
                children: [
                  const Expanded(child: Text('Total', style: TextStyle(fontWeight: FontWeight.w700))),
                  Text(formatBs(total), style: textTheme.titleMedium),
                ],
              ),
            ),
          ],
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
