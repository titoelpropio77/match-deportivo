import 'package:flutter/material.dart';

import '../../../models/featured_court_model.dart';

/// Card showing a single highlighted court.
class FeaturedCourtCard extends StatelessWidget {
  const FeaturedCourtCard({required this.court, required this.onViewPressed, super.key});

  final FeaturedCourtModel court;
  final VoidCallback onViewPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 200,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 16 / 10,
              child: Image.network(court.imageUrl, fit: BoxFit.cover),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    court.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          court.city,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                      const Icon(Icons.star_rounded, size: 16, color: Colors.amber),
                      Text(' ${court.rating}'),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Bs. ${court.pricePerHour.toStringAsFixed(0)}/hora',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: onViewPressed,
                      child: const Text('Ver Cancha'),
                    ),
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
