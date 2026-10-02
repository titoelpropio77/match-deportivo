import 'package:flutter/material.dart';

import '../../../models/event_space_model.dart';
import '../../reserve_court/reserve_courts_screen.dart';

/// Quiet home invitation to the event spaces (grill areas, halls...): a few overlapping photos,
/// one line of copy and the lowest hourly price.
class EventSpacesInvite extends StatelessWidget {
  const EventSpacesInvite({required this.spaces, required this.onTap, super.key});

  final List<EventSpaceModel> spaces;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final minPrice = spaces.map((space) => space.pricePerHour).reduce((a, b) => a < b ? a : b);
    final photos = spaces.map((space) => space.coverUrl).whereType<String>().take(3).toList();

    return Card(
      elevation: 0,
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              if (photos.isEmpty)
                CircleAvatar(
                  backgroundColor: colors.tertiaryContainer,
                  child: Icon(Icons.celebration_outlined, color: colors.onTertiaryContainer),
                )
              else
                SizedBox(
                  width: 40.0 + (photos.length - 1) * 22,
                  height: 40,
                  child: Stack(
                    children: [
                      for (var index = 0; index < photos.length; index++)
                        Positioned(
                          left: index * 22.0,
                          child: CircleAvatar(
                            radius: 20,
                            backgroundColor: colors.surface,
                            child: CircleAvatar(
                              radius: 18,
                              backgroundColor: colors.tertiaryContainer,
                              backgroundImage: NetworkImage(photos[index]),
                              onBackgroundImageError: (_, _) {},
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '¿Celebras algo?',
                      style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Parrilleros y salones desde ${formatBs(minPrice)}/h',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
