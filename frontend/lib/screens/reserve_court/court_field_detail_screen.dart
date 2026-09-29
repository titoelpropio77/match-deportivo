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
  CourtAvailabilityModel? _availability;
  bool _loading = true;
  String? _error;
  String? _selectedStart;
  int _hours = 1;
  late SportModel _sport = widget.field.sports.first;

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
        fieldId: widget.field.id,
        date: _date,
      );
      if (!mounted) return;
      setState(() {
        _availability = availability;
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

  double get _amount => widget.field.pricePerHour * _hours;

  String? get _endTime {
    if (!_canBookHours(_hours) || _selectedStart == null) return null;
    final parts = _selectedStart!.split(':');
    final start = DateTime(2026, 1, 1, int.parse(parts[0]), int.parse(parts[1]));
    final end = start.add(Duration(hours: _hours));
    final hour = end.hour.toString().padLeft(2, '0');
    final minute = end.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  }

  @override
  Widget build(BuildContext context) {
    final field = widget.field;
    return Scaffold(
      appBar: AppBar(title: Text(field.venue.name)),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton(
            onPressed: _endTime == null
                ? null
                : () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => CourtPaymentScreen(
                          field: field,
                          sport: _sport,
                          date: _date,
                          startTime: _selectedStart!,
                          endTime: _endTime!,
                          hours: _hours,
                          courtApiService: widget.courtApiService,
                        ),
                      ),
                    );
                  },
            child: Text(
              _endTime == null ? 'Selecciona un horario' : 'Pagar ${formatBs(_amount)}',
            ),
          ),
        ),
      ),
      body: ListView(
        children: [
          if (field.venue.photos.isNotEmpty)
            SizedBox(
              height: 220,
              child: PageView(
                children: [
                  for (final photo in field.venue.photos)
                    Image.network(photo, fit: BoxFit.cover),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(field.name, style: Theme.of(context).textTheme.headlineSmall),
                Text(field.venue.address),
                const SizedBox(height: 8),
                Text('${formatBs(field.pricePerHour)} por hora'),
                const SizedBox(height: 16),
                Text('Deporte', style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final sport in field.sports)
                      ChoiceChip(
                        label: Text(sport.name),
                        selected: _sport.id == sport.id,
                        onSelected: (_) => setState(() => _sport = sport),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                Text('Fecha', style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 8),
                SizedBox(
                  height: 72,
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
                  Text(_error!)
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
                Text('Horas de reserva', style: Theme.of(context).textTheme.titleSmall),
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
                      ScaffoldMessenger.of(context).showSnackBar(
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
                    '$_selectedStart – $_endTime · ${formatBs(_amount)}',
                    style: Theme.of(context).textTheme.titleMedium,
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
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final slot in availability.slots)
              ChoiceChip(
                label: Text(slot.available ? '${slot.start}–${slot.end}' : 'Ocupado\n${slot.start}'),
                selected: selectedStart == slot.start,
                onSelected: slot.available ? (_) => onSelect(slot.start) : null,
              ),
          ],
        ),
      ],
    );
  }
}

String _dayLabel(DateTime date) {
  const days = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
  return '${days[date.weekday - 1]} ${date.day}';
}
