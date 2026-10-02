import 'package:flutter/material.dart';

import '../../models/sport_model.dart';
import '../../services/sport_api_service.dart';

/// "Mis deportes favoritos": every sport of the `sports` table as selectable chips (multi-select).
class FavoriteSportsPicker extends StatefulWidget {
  const FavoriteSportsPicker({
    required this.sportApiService,
    required this.selectedIds,
    required this.onChanged,
    this.enabled = true,
    super.key,
  });

  final SportApiService sportApiService;
  final Set<int> selectedIds;
  final ValueChanged<Set<int>> onChanged;
  final bool enabled;

  @override
  State<FavoriteSportsPicker> createState() => _FavoriteSportsPickerState();
}

class _FavoriteSportsPickerState extends State<FavoriteSportsPicker> {
  List<SportModel> _sports = const [];
  bool _loading = true;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final sports = await widget.sportApiService.list();
      if (!mounted) return;
      setState(() {
        _sports = sports;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _failed = true;
        _loading = false;
      });
    }
  }

  void _toggle(int id, bool selected) {
    final next = {...widget.selectedIds};
    selected ? next.add(id) : next.remove(id);
    widget.onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final count = widget.selectedIds.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(Icons.favorite_border_rounded, size: 20, color: colors.onSurfaceVariant),
            const SizedBox(width: 8),
            Expanded(child: Text('Mis deportes favoritos', style: textTheme.titleSmall)),
            if (count > 0)
              Text(
                count == 1 ? '1 elegido' : '$count elegidos',
                style: textTheme.bodySmall?.copyWith(color: colors.primary),
              ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          'Elige los que juegas; te mostraremos primero partidos y canchas de esos deportes.',
          style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
        ),
        const SizedBox(height: 8),
        if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2)),
          )
        else if (_failed)
          Row(
            children: [
              const Expanded(child: Text('No pudimos cargar los deportes.')),
              TextButton(onPressed: _load, child: const Text('Reintentar')),
            ],
          )
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final sport in _sports)
                FilterChip(
                  label: Text(sport.name),
                  selected: widget.selectedIds.contains(sport.id),
                  onSelected: widget.enabled ? (selected) => _toggle(sport.id, selected) : null,
                ),
            ],
          ),
      ],
    );
  }
}
