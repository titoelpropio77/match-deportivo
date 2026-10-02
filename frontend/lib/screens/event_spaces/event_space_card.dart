import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../reserve_court/reserve_courts_screen.dart';

/// Photo of an event space with a soft placeholder when it has none or fails to load.
class EventSpacePhoto extends StatelessWidget {
  const EventSpacePhoto({required this.space, this.height, super.key});

  final EventSpaceModel space;
  final double? height;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final placeholder = ColoredBox(
      color: colors.tertiaryContainer,
      child: Center(
        child: Icon(eventSpaceTypeIcon(space.type.key), size: 36, color: colors.onTertiaryContainer),
      ),
    );
    final url = space.coverUrl;
    return SizedBox(
      height: height,
      width: double.infinity,
      child: url == null
          ? placeholder
          : Image.network(url, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
    );
  }
}

/// Full-width card for the event spaces list.
class EventSpaceCard extends StatelessWidget {
  const EventSpaceCard({required this.space, required this.onTap, super.key});

  final EventSpaceModel space;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                EventSpacePhoto(space: space, height: 170),
                Positioned(
                  left: 12,
                  top: 12,
                  child: _TypePill(label: space.type.label),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(space.name, style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                  if (space.venue != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      space.venue!.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Icon(Icons.people_outline, size: 16, color: colors.onSurfaceVariant),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          'Hasta ${space.capacity} personas',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: textTheme.bodySmall,
                        ),
                      ),
                      Text.rich(
                        TextSpan(
                          children: [
                            TextSpan(
                              text: formatBs(space.pricePerHour),
                              style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            TextSpan(text: ' / hora', style: textTheme.bodySmall),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Small card for horizontal teasers (sports center detail).
class EventSpaceCompactCard extends StatelessWidget {
  const EventSpaceCompactCard({required this.space, required this.onTap, super.key});

  final EventSpaceModel space;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return SizedBox(
      width: 220,
      child: Card(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: colors.outlineVariant),
        ),
        child: InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              EventSpacePhoto(space: space, height: 96),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      space.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.titleSmall,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${space.type.label} · ${space.capacity} pers. · ${formatBs(space.pricePerHour)}/h',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TypePill extends StatelessWidget {
  const _TypePill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Text(
          label.toUpperCase(),
          style: const TextStyle(
            color: Colors.white,
            fontSize: 11,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.8,
          ),
        ),
      ),
    );
  }
}
