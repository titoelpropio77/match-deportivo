import 'package:flutter/material.dart';

import '../../../models/court_field_model.dart';
import '../../reserve_court/court_payment_screen.dart';

/// Home entry point to the courts the user booked. Only shown when there is at least one.
class MyReservationsSection extends StatelessWidget {
  const MyReservationsSection({
    required this.reservations,
    required this.onTap,
    super.key,
  });

  final List<CourtReservationModel> reservations;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final upcoming = reservations.where((item) => item.isUpcoming).toList();
    final next = upcoming.isEmpty ? null : upcoming.first;
    final subtitle = next == null
        ? 'Ver el historial de tus reservas'
        : 'Próxima: ${next.venueName ?? 'Cancha'} · '
            '${formatReservationDate(next.startsAt)} ${next.startTime}';

    return Card(
      elevation: 0,
      color: colors.primaryContainer,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              CircleAvatar(
                backgroundColor: colors.primary,
                child: Icon(Icons.event_available_rounded, color: colors.onPrimary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      upcoming.isEmpty
                          ? 'Mis reservas'
                          : 'Mis reservas (${upcoming.length} próxima${upcoming.length == 1 ? '' : 's'})',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: colors.onPrimaryContainer,
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: colors.onPrimaryContainer,
                          ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onPrimaryContainer),
            ],
          ),
        ),
      ),
    );
  }
}
