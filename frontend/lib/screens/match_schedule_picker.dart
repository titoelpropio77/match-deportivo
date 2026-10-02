import 'package:flutter/material.dart';

/// "When is it played?": pick a day from chips, then a start and an end time in 30-minute steps.
/// Times are limited to the venue's opening hours when known.
class MatchSchedulePicker extends StatefulWidget {
  const MatchSchedulePicker({
    required this.start,
    required this.end,
    required this.onChanged,
    this.openingTime,
    this.closingTime,
    this.now,
    super.key,
  });

  final DateTime? start;
  final DateTime? end;

  /// Venue hours as "HH:mm" or "HH:mm:ss"; without them 06:00–24:00 is offered.
  final String? openingTime;
  final String? closingTime;

  /// Called with both values null while the selection is incomplete.
  final void Function(DateTime? start, DateTime? end) onChanged;

  /// Current time, injectable for tests.
  final DateTime? now;

  @override
  State<MatchSchedulePicker> createState() => _MatchSchedulePickerState();
}

class _MatchSchedulePickerState extends State<MatchSchedulePicker> {
  static const _step = 30;
  static const _visibleDays = 14;
  static const _maxDurationMinutes = 4 * 60;

  late DateTime? _day = widget.start == null ? null : DateUtils.dateOnly(widget.start!);

  /// Minutes since midnight.
  late int? _startMinute = widget.start == null ? null : widget.start!.hour * 60 + widget.start!.minute;
  late int? _endMinute = _minutesOfEnd(widget.start, widget.end);

  DateTime get _now => widget.now ?? DateTime.now();
  DateTime get _today => DateUtils.dateOnly(_now);

  int get _opening => _parseMinutes(widget.openingTime) ?? 6 * 60;

  int get _closing {
    final closing = _parseMinutes(widget.closingTime);
    if (closing == null) return 24 * 60;
    // "00:00" (or anything before opening) means the venue closes at midnight.
    return closing <= _opening ? 24 * 60 : closing;
  }

  @override
  void didUpdateWidget(MatchSchedulePicker oldWidget) {
    super.didUpdateWidget(oldWidget);
    final hoursChanged = oldWidget.openingTime != widget.openingTime || oldWidget.closingTime != widget.closingTime;
    if (hoursChanged && _startMinute != null && !_startOptions().contains(_startMinute)) {
      // The newly selected venue is closed at that time.
      _startMinute = null;
      _endMinute = null;
      WidgetsBinding.instance.addPostFrameCallback((_) => _notify());
    }
  }

  List<int> _startOptions() {
    final day = _day;
    if (day == null) return const [];
    final isToday = DateUtils.isSameDay(day, _today);
    final nowMinute = _now.hour * 60 + _now.minute;
    return [
      for (var minute = _opening; minute + _step <= _closing; minute += _step)
        if (!isToday || minute > nowMinute) minute,
    ];
  }

  List<int> _endOptions() {
    final start = _startMinute;
    if (start == null) return const [];
    return [
      for (var minute = start + _step; minute <= _closing && minute - start <= _maxDurationMinutes; minute += _step)
        minute,
    ];
  }

  void _selectDay(DateTime day) {
    setState(() {
      _day = DateUtils.dateOnly(day);
      // Keep the same time on the new day when it is still possible.
      if (_startMinute != null && !_startOptions().contains(_startMinute)) {
        _startMinute = null;
        _endMinute = null;
      }
    });
    _notify();
  }

  void _selectStart(int minute) {
    final previousDuration = (_startMinute != null && _endMinute != null) ? _endMinute! - _startMinute! : 60;
    setState(() {
      _startMinute = minute;
      final ends = _endOptions();
      // Keep the chosen length (1 h by default) if it still fits.
      final keep = minute + previousDuration;
      _endMinute = ends.contains(keep) ? keep : (ends.contains(minute + 60) ? minute + 60 : ends.firstOrNull);
    });
    _notify();
  }

  void _selectEnd(int minute) {
    setState(() => _endMinute = minute);
    _notify();
  }

  Future<void> _pickOtherDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _day ?? _today,
      firstDate: _today,
      lastDate: _today.add(const Duration(days: 365)),
      helpText: 'Fecha del partido',
    );
    if (picked != null) _selectDay(picked);
  }

  void _notify() {
    final day = _day;
    final start = _startMinute;
    final end = _endMinute;
    if (day == null || start == null || end == null) {
      widget.onChanged(null, null);
      return;
    }
    widget.onChanged(_at(day, start), _at(day, end));
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final day = _day;
    final starts = _startOptions();
    final ends = _endOptions();
    final days = [for (var i = 0; i < _visibleDays; i++) _today.add(Duration(days: i))];
    final extraDay = day != null && !days.any((item) => DateUtils.isSameDay(item, day)) ? day : null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('¿Cuándo se juega?', style: textTheme.titleMedium),
        if (widget.openingTime != null && widget.closingTime != null)
          Text(
            'El centro atiende de ${_label(_opening)} a ${_label(_closing)}.',
            style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
          ),
        const SizedBox(height: 12),
        _StepTitle(number: 1, text: 'Elige el día', done: day != null),
        const SizedBox(height: 8),
        // Sized by its content so the chips grow with the user's font size.
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (extraDay != null) _DayChip(day: extraDay, today: _today, selected: true, onTap: () {}),
                for (final item in days)
                  _DayChip(
                    day: item,
                    today: _today,
                    selected: day != null && DateUtils.isSameDay(item, day),
                    onTap: () => _selectDay(item),
                  ),
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ActionChip(
                    avatar: const Icon(Icons.calendar_month_outlined, size: 18),
                    label: const Text('Otra fecha'),
                    onPressed: _pickOtherDate,
                  ),
                ),
              ],
            ),
          ),
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          alignment: Alignment.topCenter,
          child: day == null
              ? const SizedBox(width: double.infinity)
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 16),
                    _StepTitle(number: 2, text: 'Hora de inicio', done: _startMinute != null),
                    const SizedBox(height: 4),
                    if (starts.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Text(
                          DateUtils.isSameDay(day, _today)
                              ? 'Ya no quedan horarios para hoy. Elige otro día.'
                              : 'No hay horarios disponibles ese día.',
                          style: textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
                        ),
                      )
                    else
                      for (final period in _periods)
                        if (starts.any(period.contains))
                          _TimeGroup(
                            title: period.title,
                            icon: period.icon,
                            times: starts.where(period.contains).toList(),
                            selected: _startMinute,
                            label: _label,
                            onSelected: _selectStart,
                          ),
                  ],
                ),
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          alignment: Alignment.topCenter,
          child: _startMinute == null || ends.isEmpty
              ? const SizedBox(width: double.infinity)
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 16),
                    _StepTitle(number: 3, text: 'Hora de término', done: _endMinute != null),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final minute in ends)
                          ChoiceChip(
                            label: Text('${_label(minute)} · ${_duration(minute - _startMinute!)}'),
                            selected: _endMinute == minute,
                            onSelected: (_) => _selectEnd(minute),
                          ),
                      ],
                    ),
                  ],
                ),
        ),
        if (day != null && _startMinute != null && _endMinute != null) ...[
          const SizedBox(height: 16),
          _Summary(
            text: _longDate(day),
            detail: '${_label(_startMinute!)} – ${_label(_endMinute!)} · ${_duration(_endMinute! - _startMinute!)}',
          ),
        ],
      ],
    );
  }

  static const _periods = [
    _Period('Por la mañana', Icons.wb_twilight_rounded, 0, 12 * 60),
    _Period('Por la tarde', Icons.wb_sunny_outlined, 12 * 60, 19 * 60),
    _Period('Por la noche', Icons.nights_stay_outlined, 19 * 60, 24 * 60),
  ];
}

class _Period {
  const _Period(this.title, this.icon, this.from, this.to);

  final String title;
  final IconData icon;
  final int from;
  final int to;

  bool contains(int minute) => minute >= from && minute < to;
}

class _StepTitle extends StatelessWidget {
  const _StepTitle({required this.number, required this.text, required this.done});

  final int number;
  final String text;
  final bool done;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: [
        CircleAvatar(
          radius: 11,
          backgroundColor: done ? colors.primary : colors.surfaceContainerHighest,
          child: done
              ? Icon(Icons.check, size: 14, color: colors.onPrimary)
              : Text('$number', style: TextStyle(fontSize: 12, color: colors.onSurfaceVariant)),
        ),
        const SizedBox(width: 8),
        Text(text, style: Theme.of(context).textTheme.titleSmall),
      ],
    );
  }
}

class _DayChip extends StatelessWidget {
  const _DayChip({required this.day, required this.today, required this.selected, required this.onTap});

  final DateTime day;
  final DateTime today;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final diff = day.difference(today).inDays;
    final top = switch (diff) {
      0 => 'Hoy',
      1 => 'Mañana',
      _ => _weekdays[day.weekday - 1],
    };
    final foreground = selected ? colors.onPrimary : colors.onSurface;

    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Material(
        color: selected ? colors.primary : colors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? colors.primary : colors.outlineVariant),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Container(
            width: 68,
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(top, style: textTheme.labelSmall?.copyWith(color: foreground)),
                Text(
                  '${day.day}',
                  style: textTheme.titleMedium?.copyWith(color: foreground, fontWeight: FontWeight.w700),
                ),
                Text(_months[day.month - 1], style: textTheme.labelSmall?.copyWith(color: foreground)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TimeGroup extends StatelessWidget {
  const _TimeGroup({
    required this.title,
    required this.icon,
    required this.times,
    required this.selected,
    required this.label,
    required this.onSelected,
  });

  final String title;
  final IconData icon;
  final List<int> times;
  final int? selected;
  final String Function(int minute) label;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: colors.onSurfaceVariant),
              const SizedBox(width: 4),
              Text(title, style: Theme.of(context).textTheme.labelLarge?.copyWith(color: colors.onSurfaceVariant)),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final minute in times)
                ChoiceChip(
                  label: Text(label(minute)),
                  selected: selected == minute,
                  showCheckmark: false,
                  onSelected: (_) => onSelected(minute),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.text, required this.detail});

  final String text;
  final String detail;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: colors.primaryContainer, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Icon(Icons.event_available_rounded, color: colors.onPrimaryContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  text,
                  style: TextStyle(color: colors.onPrimaryContainer, fontWeight: FontWeight.w700),
                ),
                Text(detail, style: TextStyle(color: colors.onPrimaryContainer)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

const _weekdays = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
const _months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

int? _parseMinutes(String? time) {
  if (time == null) return null;
  final parts = time.split(':');
  if (parts.length < 2) return null;
  final hour = int.tryParse(parts[0]);
  final minute = int.tryParse(parts[1]);
  if (hour == null || minute == null) return null;
  return hour * 60 + minute;
}

int? _minutesOfEnd(DateTime? start, DateTime? end) {
  if (start == null || end == null) return null;
  return end.difference(DateUtils.dateOnly(start)).inMinutes;
}

DateTime _at(DateTime day, int minutes) => day.add(Duration(minutes: minutes));

/// 1170 → "19:30", 1440 → "24:00".
String _label(int minutes) =>
    '${(minutes ~/ 60).toString().padLeft(2, '0')}:${(minutes % 60).toString().padLeft(2, '0')}';

/// 90 → "1 h 30", 60 → "1 h", 30 → "30 min".
String _duration(int minutes) {
  final hours = minutes ~/ 60;
  final rest = minutes % 60;
  if (hours == 0) return '$rest min';
  return rest == 0 ? '$hours h' : '$hours h $rest';
}

String _longDate(DateTime date) {
  const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  const months = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
  ];
  return '${days[date.weekday - 1]} ${date.day} de ${months[date.month - 1]}';
}
