import 'package:flutter/material.dart';

import '../../../models/match_model.dart';

/// Card representing a single match. Shows a "Unirse" action unless [showJoinButton] is false.
class MatchCard extends StatelessWidget {
  const MatchCard({
    required this.match,
    required this.isJoining,
    required this.onJoin,
    this.showJoinButton = true,
    this.onTap,
    super.key,
  });

  final MatchModel match;
  final bool isJoining;
  final VoidCallback onJoin;
  final bool showJoinButton;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final date = MaterialLocalizations.of(context)
        .formatMediumDate(match.scheduledAt.toLocal());
    final time = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.scheduledAt.toLocal()));

    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      match.sport?.name ?? 'Deporte',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  _SlotsBadge(slots: match.missingPlayers),
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 18,
                runSpacing: 8,
                children: [
                  _InfoItem(icon: Icons.calendar_today_outlined, label: date),
                  _InfoItem(icon: Icons.schedule_outlined, label: time),
                  _InfoItem(
                    icon: Icons.location_on_outlined,
                    label: match.court?.name ?? 'Cancha',
                  ),
                ],
              ),
              const SizedBox(height: 18),
              if (showJoinButton)
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: isJoining ? null : onJoin,
                    icon: isJoining
                        ? const SizedBox.square(
                            dimension: 17,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.sports_soccer_outlined),
                    label: Text(isJoining ? 'Uniendo...' : 'Unirse'),
                    style: FilledButton.styleFrom(
                      backgroundColor: colors.primary,
                      padding: const EdgeInsets.symmetric(vertical: 13),
                    ),
                  ),
                )
              else
                Row(
                  children: [
                    Icon(
                      Icons.check_circle_rounded,
                      size: 18,
                      color: colors.primary,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Ya estás inscrito',
                      style: TextStyle(
                        color: colors.primary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SlotsBadge extends StatelessWidget {
  const _SlotsBadge({required this.slots});

  final int slots;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: colors.secondaryContainer,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        '$slots ${slots == 1 ? 'cupo' : 'cupos'}',
        style: TextStyle(
          color: colors.onSecondaryContainer,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _InfoItem extends StatelessWidget {
  const _InfoItem({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 6),
        Text(label),
      ],
    );
  }
}
