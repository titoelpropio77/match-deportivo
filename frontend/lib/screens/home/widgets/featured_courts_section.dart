import 'package:flutter/material.dart';

import '../../../models/featured_court_model.dart';
import 'featured_court_card.dart';

/// Horizontal list of highlighted courts with a section title.
class FeaturedCourtsSection extends StatelessWidget {
  const FeaturedCourtsSection({
    required this.courts,
    required this.onCourtPressed,
    super.key,
  });

  final List<FeaturedCourtModel> courts;
  final ValueChanged<FeaturedCourtModel> onCourtPressed;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Canchas Destacadas en Santa Cruz',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 250,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: courts.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final court = courts[index];
              return FeaturedCourtCard(
                court: court,
                onViewPressed: () => onCourtPressed(court),
              );
            },
          ),
        ),
      ],
    );
  }
}
