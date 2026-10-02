import 'package:flutter/material.dart';

import '../models/court_field_model.dart';
import 'reserve_court/court_payment_screen.dart';

/// Where the court of a new match comes from.
enum MatchCourtSource {
  /// Booked in the app: pick one of the user's reservations to fill venue, courts and time.
  reservedInApp,

  /// Not booked yet: go to "Reservar cancha" and come back.
  bookNow,

  /// Booked personally (at the venue, by phone): fill the details by hand.
  bookedElsewhere,
}

/// A block of time the user has booked at one venue on one day, possibly on several courts
/// (e.g. Cancha 1 and Cancha 2, 17:00–19:00). Consecutive or overlapping ranges are merged.
class ReservedSlot {
  const ReservedSlot({
    required this.venueId,
    required this.venueName,
    required this.start,
    required this.end,
    required this.fieldIds,
    required this.fieldNames,
    required this.isPaid,
    this.address,
    this.openingTime,
    this.closingTime,
    this.sportName,
    this.bookingCode,
  });

  final int venueId;
  final String venueName;
  final String? address;
  final String? openingTime;
  final String? closingTime;
  final DateTime start;
  final DateTime end;
  final Set<int> fieldIds;
  final List<String> fieldNames;
  final String? sportName;
  final String? bookingCode;
  final bool isPaid;

  /// Upcoming, not cancelled reservations of the user, as pickable blocks sorted by start.
  static List<ReservedSlot> fromReservations(List<CourtReservationModel> reservations, {DateTime? now}) {
    final current = now ?? DateTime.now();
    final active = reservations
        .where((item) => !item.isCancelled && item.field != null && item.endsAt.isAfter(current))
        .where((item) => item.isPaid || !(item.paymentExpiresAt?.isBefore(current) ?? false))
        .toList()
      ..sort((a, b) => a.startsAt.compareTo(b.startsAt));

    // Same venue and day, overlapping or back-to-back → one block.
    final slots = <ReservedSlot>[];
    for (final item in active) {
      final venue = item.field!.venue;
      final index = slots.lastIndexWhere(
        (slot) =>
            slot.venueId == venue.id &&
            DateUtils.isSameDay(slot.start, item.startsAt) &&
            !item.startsAt.isAfter(slot.end),
      );
      if (index < 0) {
        slots.add(ReservedSlot(
          venueId: venue.id,
          venueName: venue.name,
          address: venue.address,
          openingTime: venue.openingTime,
          closingTime: venue.closingTime,
          start: item.startsAt,
          end: item.endsAt,
          fieldIds: {item.field!.id},
          fieldNames: [item.fieldName ?? item.field!.name],
          sportName: item.sportName,
          bookingCode: item.bookingCode,
          isPaid: item.isPaid,
        ));
        continue;
      }
      final slot = slots[index];
      final name = item.fieldName ?? item.field!.name;
      slots[index] = ReservedSlot(
        venueId: slot.venueId,
        venueName: slot.venueName,
        address: slot.address,
        openingTime: slot.openingTime,
        closingTime: slot.closingTime,
        start: slot.start,
        end: item.endsAt.isAfter(slot.end) ? item.endsAt : slot.end,
        fieldIds: {...slot.fieldIds, item.field!.id},
        fieldNames: slot.fieldNames.contains(name) ? slot.fieldNames : [...slot.fieldNames, name],
        sportName: slot.sportName ?? item.sportName,
        bookingCode: slot.bookingCode ?? item.bookingCode,
        isPaid: slot.isPaid && item.isPaid,
      );
    }
    return slots;
  }
}

String _hhmm(DateTime time) =>
    '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';

String reservedSlotTime(ReservedSlot slot) =>
    '${formatReservationDate(slot.start)} · ${_hhmm(slot.start)}–${_hhmm(slot.end)}';

/// "¿Ya tienes la cancha reservada?" with its three answers, and the matching content below:
/// the user's reservations, the button to book now, or nothing (manual form).
class MatchCourtSourceSection extends StatelessWidget {
  const MatchCourtSourceSection({
    required this.source,
    required this.onSourceChanged,
    required this.slots,
    required this.loadingSlots,
    required this.picked,
    required this.onPick,
    required this.onBookNow,
    required this.enabled,
    this.slotsError,
    this.onRetry,
    super.key,
  });

  final MatchCourtSource? source;
  final ValueChanged<MatchCourtSource> onSourceChanged;
  final List<ReservedSlot> slots;
  final bool loadingSlots;
  final String? slotsError;
  final VoidCallback? onRetry;
  final ReservedSlot? picked;
  final ValueChanged<ReservedSlot?> onPick;
  final VoidCallback onBookNow;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('¿Ya tienes la cancha reservada?', style: textTheme.titleMedium),
        const SizedBox(height: 8),
        _SourceOption(
          icon: Icons.event_available_rounded,
          title: 'Sí, la reservé en la app',
          subtitle: 'Elige tu reserva y completamos la cancha y el horario.',
          selected: source == MatchCourtSource.reservedInApp,
          onTap: enabled ? () => onSourceChanged(MatchCourtSource.reservedInApp) : null,
        ),
        _SourceOption(
          icon: Icons.add_business_outlined,
          title: 'No, quiero reservarla ahora',
          subtitle: 'Te llevamos a Reservar cancha y vuelves aquí para seguir.',
          selected: source == MatchCourtSource.bookNow,
          onTap: enabled ? () => onSourceChanged(MatchCourtSource.bookNow) : null,
        ),
        _SourceOption(
          icon: Icons.storefront_outlined,
          title: 'Ya la reservé por mi cuenta',
          subtitle: 'En el centro o por teléfono. Completa los datos abajo.',
          selected: source == MatchCourtSource.bookedElsewhere,
          onTap: enabled ? () => onSourceChanged(MatchCourtSource.bookedElsewhere) : null,
        ),
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          alignment: Alignment.topCenter,
          child: switch (source) {
            MatchCourtSource.reservedInApp => _buildReservations(context),
            MatchCourtSource.bookNow => Padding(
                padding: const EdgeInsets.only(top: 8),
                child: FilledButton.icon(
                  onPressed: enabled ? onBookNow : null,
                  icon: const Icon(Icons.sports_tennis_outlined),
                  label: const Text('Reservar cancha'),
                ),
              ),
            _ => const SizedBox(width: double.infinity),
          },
        ),
      ],
    );
  }

  Widget _buildReservations(BuildContext context) {
    final picked = this.picked;
    if (picked != null) {
      return Padding(
        padding: const EdgeInsets.only(top: 8),
        child: _PickedSlotCard(slot: picked, onChange: enabled ? () => onPick(null) : null),
      );
    }
    if (loadingSlots) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (slotsError != null) {
      return Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Row(
          children: [
            Expanded(child: Text(slotsError!)),
            TextButton(onPressed: onRetry, child: const Text('Reintentar')),
          ],
        ),
      );
    }
    if (slots.isEmpty) {
      return Card(
        margin: const EdgeInsets.only(top: 8),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('No tienes reservas próximas en la app.'),
              const SizedBox(height: 8),
              FilledButton.tonalIcon(
                onPressed: enabled ? onBookNow : null,
                icon: const Icon(Icons.sports_tennis_outlined),
                label: const Text('Reservar cancha ahora'),
              ),
            ],
          ),
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 8),
        Text('Elige la reserva para el partido', style: Theme.of(context).textTheme.titleSmall),
        for (final slot in slots)
          Card(
            margin: const EdgeInsets.only(top: 8),
            child: ListTile(
              leading: const CircleAvatar(child: Icon(Icons.stadium_outlined)),
              title: Text(slot.venueName),
              subtitle: Text('${reservedSlotTime(slot)}\n${slot.fieldNames.join(', ')}'),
              isThreeLine: true,
              trailing: slot.isPaid
                  ? null
                  : Tooltip(
                      message: 'Pago pendiente',
                      child: Icon(Icons.hourglass_top_rounded, color: Theme.of(context).colorScheme.tertiary),
                    ),
              onTap: enabled ? () => onPick(slot) : null,
            ),
          ),
      ],
    );
  }
}

class _SourceOption extends StatelessWidget {
  const _SourceOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      color: selected ? colors.primaryContainer : colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: selected ? colors.primary : colors.outlineVariant, width: selected ? 2 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Icon(icon, color: selected ? colors.primary : colors.onSurfaceVariant),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
                    Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
              ),
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: selected ? colors.primary : colors.outline,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PickedSlotCard extends StatelessWidget {
  const _PickedSlotCard({required this.slot, required this.onChange});

  final ReservedSlot slot;
  final VoidCallback? onChange;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.secondaryContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.check_circle, color: colors.onSecondaryContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Cancha reservada', style: textTheme.labelMedium),
                Text(slot.venueName, style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                Text(reservedSlotTime(slot)),
                Text(slot.fieldNames.join(', ')),
                if (!slot.isPaid)
                  Text('Pago pendiente', style: textTheme.bodySmall?.copyWith(color: colors.error)),
              ],
            ),
          ),
          TextButton(onPressed: onChange, child: const Text('Cambiar')),
        ],
      ),
    );
  }
}
