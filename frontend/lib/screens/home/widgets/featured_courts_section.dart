import 'package:flutter/material.dart';

import '../../../models/featured_court_model.dart';
import 'featured_court_card.dart';

/// Horizontal list of highlighted sports centers (best rated and recently added) with loading,
/// error and empty states.
class FeaturedCourtsSection extends StatelessWidget {
  const FeaturedCourtsSection({
    required this.courts,
    required this.loading,
    required this.onCourtPressed,
    this.error,
    this.onRetry,
    super.key,
  });

  /// Room for the photo plus four text lines and the button.
  static const listHeight = 278.0;

  final List<FeaturedCourtModel> courts;
  final bool loading;
  final String? error;
  final VoidCallback? onRetry;
  final ValueChanged<FeaturedCourtModel> onCourtPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasHighlights = courts.any((court) => court.highlight != null);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Complejos destacados',
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        if (!loading && error == null && courts.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              hasHighlights
                  ? 'Los mejor calificados y los recién incorporados'
                  : 'Centros deportivos disponibles para reservar',
              style: theme.textTheme.bodySmall,
            ),
          ),
        const SizedBox(height: 12),
        _buildContent(context),
      ],
    );
  }

  Widget _buildContent(BuildContext context) {
    if (loading) {
      return const SizedBox(
        height: listHeight,
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (error != null) {
      return _Message(
        icon: Icons.cloud_off_outlined,
        text: error!,
        action: onRetry == null
            ? null
            : TextButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Reintentar'),
              ),
      );
    }

    if (courts.isEmpty) {
      return const _Message(
        icon: Icons.stadium_outlined,
        text: 'Todavía no hay complejos deportivos registrados.',
      );
    }

    return SizedBox(
      height: listHeight,
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
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text, this.action});

  final IconData icon;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Icon(icon, color: colors.onSurfaceVariant),
          const SizedBox(height: 8),
          Text(text, textAlign: TextAlign.center),
          if (action != null) action!,
        ],
      ),
    );
  }
}
