import 'package:flutter/material.dart';

import '../../../models/sport_model.dart';

/// Date and sport filters for the open-matches list.
class OpenMatchFilters extends StatelessWidget {
  const OpenMatchFilters({
    required this.sports,
    required this.selectedDate,
    required this.selectedSportId,
    required this.onDateChanged,
    required this.onSportChanged,
    super.key,
  });

  final List<SportModel> sports;
  final DateTime? selectedDate;
  final int? selectedSportId;
  final ValueChanged<DateTime?> onDateChanged;
  final ValueChanged<int?> onSportChanged;

  String _dateLabel(BuildContext context) {
    if (selectedDate == null) return 'Fecha';
    return MaterialLocalizations.of(context).formatMediumDate(selectedDate!);
  }

  String get _sportLabel {
    if (selectedSportId == null) return 'Deporte';
    for (final sport in sports) {
      if (sport.id == selectedSportId) return sport.name;
    }
    return 'Deporte';
  }

  Future<void> _pickDate(BuildContext context) async {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: selectedDate ?? today,
      firstDate: today,
      lastDate: today.add(const Duration(days: 90)),
      helpText: 'Filtrar por fecha',
      cancelText: 'Cancelar',
      confirmText: 'Aplicar',
    );
    if (picked != null) {
      onDateChanged(picked);
    }
  }

  Future<void> _pickSport(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                title: Text(
                  'Tipo de partido',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              RadioListTile<int?>(
                title: const Text('Todos'),
                value: null,
                groupValue: selectedSportId,
                onChanged: (value) {
                  Navigator.of(context).pop();
                  onSportChanged(value);
                },
              ),
              for (final sport in sports)
                RadioListTile<int?>(
                  title: Text(sport.name),
                  value: sport.id,
                  groupValue: selectedSportId,
                  onChanged: (value) {
                    Navigator.of(context).pop();
                    onSportChanged(value);
                  },
                ),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        InputChip(
          avatar: const Icon(Icons.calendar_today_outlined, size: 16),
          label: Text(_dateLabel(context)),
          selected: selectedDate != null,
          onPressed: () => _pickDate(context),
          onDeleted: selectedDate == null ? null : () => onDateChanged(null),
          deleteIcon: const Icon(Icons.close, size: 16),
        ),
        InputChip(
          avatar: const Icon(Icons.sports_soccer_outlined, size: 16),
          label: Text(_sportLabel),
          selected: selectedSportId != null,
          onPressed: () => _pickSport(context),
          onDeleted: selectedSportId == null ? null : () => onSportChanged(null),
          deleteIcon: const Icon(Icons.close, size: 16),
        ),
      ],
    );
  }
}
