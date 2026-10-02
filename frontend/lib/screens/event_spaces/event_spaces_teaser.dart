import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../../services/event_space_api_service.dart';
import 'event_space_card.dart';
import 'event_space_detail_screen.dart';

/// Discreet "also for your events" section of a sports center: only shows up when the
/// center rents event spaces, and stays hidden on errors.
class EventSpacesTeaser extends StatefulWidget {
  const EventSpacesTeaser({required this.courtId, required this.eventSpaceApiService, super.key});

  final int courtId;
  final EventSpaceApiService eventSpaceApiService;

  @override
  State<EventSpacesTeaser> createState() => _EventSpacesTeaserState();
}

class _EventSpacesTeaserState extends State<EventSpacesTeaser> {
  List<EventSpaceModel> _spaces = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final spaces = await widget.eventSpaceApiService.list(courtId: widget.courtId);
      if (mounted) setState(() => _spaces = spaces);
    } catch (_) {
      // Optional extra: nothing to show.
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_spaces.isEmpty) return const SizedBox.shrink();
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 28),
        Row(
          children: [
            Icon(Icons.celebration_outlined, size: 18, color: colors.tertiary),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'También para tus eventos',
                style: textTheme.titleSmall?.copyWith(color: colors.tertiary),
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          'Celebra después del partido: espacios que este centro alquila por hora.',
          style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 160,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _spaces.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final space = _spaces[index];
              return EventSpaceCompactCard(
                space: space,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => EventSpaceDetailScreen(
                      space: space,
                      eventSpaceApiService: widget.eventSpaceApiService,
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
