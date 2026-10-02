import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../../services/event_space_api_service.dart';
import '../reserve_court/reserve_courts_screen.dart';
import 'event_space_card.dart';
import 'event_space_payment_screen.dart';

/// Event space presentation (photo, capacity, what it includes, rules) and the booking form:
/// day, start time, duration, guests and kind of event.
class EventSpaceDetailScreen extends StatefulWidget {
  const EventSpaceDetailScreen({
    required this.space,
    required this.eventSpaceApiService,
    this.initialDate,
    super.key,
  });

  final EventSpaceModel space;
  final EventSpaceApiService eventSpaceApiService;
  final DateTime? initialDate;

  @override
  State<EventSpaceDetailScreen> createState() => _EventSpaceDetailScreenState();
}

class _EventSpaceDetailScreenState extends State<EventSpaceDetailScreen> {
  static const _maxHours = 12;

  late DateTime _date = DateUtils.dateOnly(widget.initialDate ?? DateTime.now());
  late int _hours = widget.space.minHours;
  late int _guests = widget.space.capacity < 10 ? widget.space.capacity : 10;
  String? _start;
  String? _eventType;
  final _notes = TextEditingController();

  EventSpaceAvailabilityModel? _availability;
  bool _loading = true;
  String? _error;

  EventSpaceModel get _space => widget.space;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final availability = await widget.eventSpaceApiService.availability(spaceId: _space.id, date: _date);
      if (!mounted) return;
      setState(() {
        _availability = availability;
        if (!availability.startsFor(_hours).contains(_start)) _start = null;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is EventSpaceApiException ? error.message : 'No pudimos cargar los horarios.';
        _loading = false;
      });
    }
  }

  int get _longestStay {
    final slots = _availability?.slots.length ?? _maxHours;
    return slots < _maxHours ? slots : _maxHours;
  }

  void _setHours(int hours) {
    setState(() {
      _hours = hours;
      if (!(_availability?.startsFor(hours).contains(_start) ?? false)) _start = null;
    });
  }

  double get _total => _space.pricePerHour * _hours;

  String _endOf(String start) {
    final hour = int.parse(start.split(':')[0]) + _hours;
    return '${hour.toString().padLeft(2, '0')}:00';
  }

  Future<void> _continue() async {
    final start = _start;
    if (start == null) return;
    final booked = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EventSpacePaymentScreen(
          space: _space,
          request: EventBookingRequest(
            date: _date,
            startTime: start,
            endTime: _endOf(start),
            hours: _hours,
            guests: _guests,
            eventType: _eventType,
            notes: _notes.text,
          ),
          eventSpaceApiService: widget.eventSpaceApiService,
        ),
      ),
    );
    if (!mounted) return;
    if (booked == true) {
      Navigator.of(context).pop(true);
      return;
    }
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final venue = _space.venue;
    final start = _start;

    return Scaffold(
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 16, 12),
          decoration: BoxDecoration(
            color: colors.surface,
            border: Border(top: BorderSide(color: colors.outlineVariant)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(formatBs(_total), style: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                    Text(
                      start == null
                          ? '$_hours ${_hours == 1 ? 'hora' : 'horas'} · elige el horario'
                          : '$start–${_endOf(start)} · $_guests personas',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              FilledButton(
                onPressed: start == null ? null : _continue,
                child: const Text('Reservar'),
              ),
            ],
          ),
        ),
      ),
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            expandedHeight: 260,
            foregroundColor: Colors.white,
            backgroundColor: colors.inverseSurface,
            title: Text(_space.name),
            flexibleSpace: FlexibleSpaceBar(
              titlePadding: EdgeInsets.zero,
              title: const SizedBox.shrink(),
              background: Stack(
                fit: StackFit.expand,
                children: [
                  EventSpacePhoto(space: _space),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.black.withValues(alpha: 0.45),
                          Colors.transparent,
                          Colors.black.withValues(alpha: 0.35),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            sliver: SliverList.list(
              children: [
                Text(
                  _space.type.label.toUpperCase(),
                  style: textTheme.labelSmall?.copyWith(color: colors.tertiary, letterSpacing: 1.2),
                ),
                const SizedBox(height: 4),
                Text(_space.name, style: textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
                if (venue != null) ...[
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.place_outlined, size: 16, color: colors.onSurfaceVariant),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          venue.address.isEmpty ? venue.name : '${venue.name} · ${venue.address}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 20),
                _Facts(space: _space),
                if (_space.description != null && _space.description!.trim().isNotEmpty) ...[
                  const SizedBox(height: 20),
                  Text(_space.description!, style: textTheme.bodyMedium?.copyWith(height: 1.45)),
                ],
                if (_space.amenities.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text('Incluye', style: textTheme.titleSmall),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final amenity in _space.amenities)
                        Chip(
                          avatar: Icon(eventAmenityIcon(amenity.key), size: 16),
                          label: Text(amenity.label),
                          visualDensity: VisualDensity.compact,
                          side: BorderSide(color: colors.outlineVariant),
                          backgroundColor: colors.surface,
                        ),
                    ],
                  ),
                ],
                if (_space.rules != null && _space.rules!.trim().isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Theme(
                    data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      leading: const Icon(Icons.rule_outlined),
                      title: const Text('Normas del espacio'),
                      childrenPadding: const EdgeInsets.only(bottom: 8),
                      expandedAlignment: Alignment.centerLeft,
                      children: [Text(_space.rules!, style: textTheme.bodyMedium)],
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                const Divider(),
                const SizedBox(height: 16),
                Text('Arma tu evento', style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: 16),
                Text('¿Qué celebras?', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final kind in eventKinds)
                      ChoiceChip(
                        label: Text(kind.label),
                        selected: _eventType == kind.key,
                        onSelected: (selected) => setState(() => _eventType = selected ? kind.key : null),
                      ),
                  ],
                ),
                const SizedBox(height: 20),
                Text('Día', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                SizedBox(
                  height: 48,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: 30,
                    separatorBuilder: (_, _) => const SizedBox(width: 8),
                    itemBuilder: (context, index) {
                      final day = DateUtils.dateOnly(DateTime.now()).add(Duration(days: index));
                      return ChoiceChip(
                        label: Text(_dayLabel(day, index)),
                        selected: DateUtils.isSameDay(day, _date),
                        onSelected: (_) {
                          setState(() => _date = day);
                          _load();
                        },
                      );
                    },
                  ),
                ),
                const SizedBox(height: 20),
                _Stepper(
                  label: 'Duración',
                  value: '$_hours ${_hours == 1 ? 'hora' : 'horas'}',
                  hint: _space.minHours > 1 ? 'Mínimo ${_space.minHours} horas' : null,
                  onMinus: _hours > _space.minHours ? () => _setHours(_hours - 1) : null,
                  onPlus: _hours < _longestStay ? () => _setHours(_hours + 1) : null,
                ),
                const SizedBox(height: 12),
                _Stepper(
                  label: 'Personas',
                  value: '$_guests',
                  hint: 'Máximo ${_space.capacity}',
                  onMinus: _guests > 1 ? () => setState(() => _guests--) : null,
                  onPlus: _guests < _space.capacity ? () => setState(() => _guests++) : null,
                ),
                const SizedBox(height: 20),
                Text('Hora de inicio', style: textTheme.titleSmall),
                const SizedBox(height: 8),
                if (_loading)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_error != null)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_error!),
                      TextButton(onPressed: _load, child: const Text('Reintentar')),
                    ],
                  )
                else
                  _StartTimes(
                    starts: _availability!.startsFor(_hours),
                    selected: _start,
                    hours: _hours,
                    onSelected: (start) => setState(() => _start = start),
                  ),
                const SizedBox(height: 20),
                TextField(
                  controller: _notes,
                  maxLength: 500,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Nota para el centro (opcional)',
                    hintText: 'Ej: llevamos torta, necesitamos una mesa extra...',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Capacity, price and minimum stay at a glance.
class _Facts extends StatelessWidget {
  const _Facts({required this.space});

  final EventSpaceModel space;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final schedule = space.openingTime != null && space.closingTime != null
        ? '${space.openingTime}–${space.closingTime}'
        : null;
    Widget fact(IconData icon, String value, String label) => Expanded(
          child: Column(
            children: [
              Icon(icon, color: colors.tertiary),
              const SizedBox(height: 6),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
              ),
            ],
          ),
        );

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          fact(Icons.people_outline, '${space.capacity}', 'personas máx.'),
          fact(Icons.payments_outlined, formatBs(space.pricePerHour), 'por hora'),
          if (schedule != null)
            fact(Icons.schedule_outlined, schedule, space.minHours > 1 ? 'mín. ${space.minHours} h' : 'horario')
          else
            fact(Icons.timelapse_outlined, '${space.minHours} h', 'mínimo'),
        ],
      ),
    );
  }
}

class _Stepper extends StatelessWidget {
  const _Stepper({
    required this.label,
    required this.value,
    required this.onMinus,
    required this.onPlus,
    this.hint,
  });

  final String label;
  final String value;
  final String? hint;
  final VoidCallback? onMinus;
  final VoidCallback? onPlus;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: textTheme.titleSmall),
              if (hint != null)
                Text(
                  hint!,
                  style: textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
                ),
            ],
          ),
        ),
        IconButton.outlined(
          tooltip: 'Menos',
          onPressed: onMinus,
          icon: const Icon(Icons.remove),
        ),
        SizedBox(
          width: 72,
          child: Text(value, textAlign: TextAlign.center, style: textTheme.titleSmall),
        ),
        IconButton.outlined(
          tooltip: 'Más',
          onPressed: onPlus,
          icon: const Icon(Icons.add),
        ),
      ],
    );
  }
}

class _StartTimes extends StatelessWidget {
  const _StartTimes({
    required this.starts,
    required this.selected,
    required this.hours,
    required this.onSelected,
  });

  final List<String> starts;
  final String? selected;
  final int hours;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    if (starts.isEmpty) {
      return Text(
        'No hay $hours ${hours == 1 ? 'hora libre' : 'horas seguidas libres'} este día. Prueba otro día o menos horas.',
        style: Theme.of(context).textTheme.bodyMedium,
      );
    }
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final start in starts)
          ChoiceChip(
            label: Text(start),
            selected: selected == start,
            onSelected: (_) => onSelected(start),
          ),
      ],
    );
  }
}

String _dayLabel(DateTime date, int index) {
  if (index == 0) return 'Hoy';
  if (index == 1) return 'Mañana';
  const days = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
  return '${days[date.weekday - 1]} ${date.day}';
}
