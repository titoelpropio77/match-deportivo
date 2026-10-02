import 'package:flutter/material.dart';

import '../../../models/featured_court_model.dart';

/// Card of a highlighted sports center. Fills the height given by its parent list,
/// so the content never overflows (the button sits at the bottom).
class FeaturedCourtCard extends StatelessWidget {
  const FeaturedCourtCard({required this.court, required this.onViewPressed, super.key});

  static const width = 210.0;
  static const imageHeight = 112.0;

  final FeaturedCourtModel court;
  final VoidCallback onViewPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final location = court.city ?? court.address;

    return SizedBox(
      width: width,
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: InkWell(
          onTap: onViewPressed,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                height: imageHeight,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    _CourtImage(url: court.photoUrl),
                    if (court.highlight != null)
                      Positioned(
                        left: 8,
                        top: 8,
                        child: _HighlightBadge(highlight: court.highlight!),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        court.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              location,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall,
                            ),
                          ),
                          const SizedBox(width: 4),
                          _Rating(rating: court.rating, count: court.reviewsCount),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        court.minPrice == null
                            ? 'Precio a consultar'
                            : 'Desde Bs. ${court.minPrice!.toStringAsFixed(0)}/hora',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      if (court.sports.isNotEmpty)
                        Text(
                          court.sports.join(' · '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                        ),
                      const Spacer(),
                      SizedBox(
                        width: double.infinity,
                        height: 36,
                        child: OutlinedButton(
                          onPressed: onViewPressed,
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            visualDensity: VisualDensity.compact,
                          ),
                          child: const Text('Ver complejo'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Network photo with a neutral placeholder while loading, when missing or when it fails.
class _CourtImage extends StatelessWidget {
  const _CourtImage({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final placeholder = ColoredBox(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Center(
        child: Icon(
          Icons.stadium_outlined,
          size: 40,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      ),
    );

    if (url == null || url!.isEmpty) return placeholder;

    return Image.network(
      url!,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => placeholder,
      loadingBuilder: (context, child, progress) => progress == null ? child : placeholder,
    );
  }
}

class _HighlightBadge extends StatelessWidget {
  const _HighlightBadge({required this.highlight});

  final FeaturedHighlight highlight;

  @override
  Widget build(BuildContext context) {
    final isTop = highlight == FeaturedHighlight.topRated;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: isTop ? Colors.amber.shade700 : Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(isTop ? Icons.emoji_events_outlined : Icons.fiber_new_outlined, size: 14, color: Colors.white),
            const SizedBox(width: 4),
            Text(
              highlight.label,
              style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

class _Rating extends StatelessWidget {
  const _Rating({required this.rating, required this.count});

  final double? rating;
  final int count;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodySmall;
    if (rating == null) {
      return Text('Sin reseñas', style: style);
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.star_rounded, size: 16, color: Colors.amber),
        Text(' ${rating!.toStringAsFixed(1)}', style: style?.copyWith(fontWeight: FontWeight.w600)),
        if (count > 0) Text(' ($count)', style: style),
      ],
    );
  }
}
