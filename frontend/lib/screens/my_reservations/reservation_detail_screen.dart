import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/user_api_service.dart';
import '../create_match_screen.dart';
import '../reserve_court/court_payment_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import '../search_teams/match_detail_screen.dart';

/// Detail of one booking of the user: every court and hour range booked together.
/// Pops `true` when it was paid or cancelled here.
class ReservationDetailScreen extends StatefulWidget {
  const ReservationDetailScreen({
    required this.group,
    required this.courtApiService,
    this.matchApiService,
    this.currentUserId,
    super.key,
  });

  final ReservationGroup group;
  final CourtApiService courtApiService;

  /// With both, the detail offers "Crear cancha" (a match on this booking) or "Ver cancha creada".
  final MatchApiService? matchApiService;
  final int? currentUserId;

  @override
  State<ReservationDetailScreen> createState() => _ReservationDetailScreenState();
}

class _ReservationDetailScreenState extends State<ReservationDetailScreen> {
  late ReservationGroup _group = widget.group;
  bool _busy = false;
  bool _changed = false;

  bool get _canCreateMatch =>
      widget.matchApiService != null &&
      widget.currentUserId != null &&
      _group.code != null &&
      !_group.isCancelled &&
      _group.isUpcoming;

  Future<void> _createMatch() async {
    final matchApi = widget.matchApiService!;
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CreateMatchScreen(
          matchApiService: matchApi,
          userApiService: UserApiService(baseUrl: matchApi.baseUrl, token: matchApi.token),
          sportApiService: SportApiService(baseUrl: matchApi.baseUrl, token: matchApi.token),
          matchLevelApiService: MatchLevelApiService(baseUrl: matchApi.baseUrl, token: matchApi.token),
          courtApiService: widget.courtApiService,
          initialBookingCode: _group.code,
        ),
      ),
    );
    if (created != true || !mounted) return;

    // Reload to learn the id of the new match ("Ver cancha creada").
    try {
      final reservations = await widget.courtApiService.myReservations();
      final updated = reservations.where((item) => item.bookingCode == _group.code).toList()
        ..sort((a, b) => a.startsAt.compareTo(b.startsAt));
      if (!mounted) return;
      if (updated.isNotEmpty) {
        final venueField = _group.field;
        setState(() => _group = ReservationGroup([for (final item in updated) _withField(item, venueField)]));
      }
    } catch (_) {
      // The match exists; the button updates next time the detail opens.
    }
    _changed = true;
    _snack('Cancha creada con esta reserva.');
  }

  void _openMatch(int matchId) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MatchDetailScreen(
          matchId: matchId,
          matchApiService: widget.matchApiService!,
          currentUserId: widget.currentUserId!,
        ),
      ),
    );
  }

  bool get _canPay =>
      _group.isPendingPayment &&
      _group.isUpcoming &&
      (_group.paymentExpiresAt?.isAfter(DateTime.now()) ?? true);

  Future<void> _pay() async {
    setState(() => _busy = true);
    try {
      final code = _group.code;
      // Ranges booked together are paid together with one QR.
      final paid = code == null
          ? [await widget.courtApiService.pay(_group.first.id)]
          : (await widget.courtApiService.payBooking(code)).reservations;
      if (!mounted) return;
      setState(() {
        final venueField = _group.field;
        _group = ReservationGroup([
          for (final item in paid) _withField(item, venueField, matchId: _group.matchId),
        ]..sort((a, b) => a.startsAt.compareTo(b.startsAt)));
        _changed = true;
      });
      _snack(_group.reservations.length == 1
          ? 'Pago confirmado. ¡Tu cancha está reservada!'
          : 'Pago confirmado. ¡Tus canchas están reservadas!');
    } catch (error) {
      _snack(error is CourtApiException ? error.message : 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    final count = _group.active.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cancelar la reserva?'),
        content: Text(
          count == 1
              ? 'El horario quedará libre para otros.'
              : 'Se cancelarán los $count horarios de esta reserva y quedarán libres para otros.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Volver'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Cancelar reserva'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      final code = _group.code;
      if (code == null) {
        await widget.courtApiService.cancelReservation(_group.first.id);
      } else {
        await widget.courtApiService.cancelBooking(code);
      }
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      _snack(error is CourtApiException ? error.message : 'No pudimos cancelar la reserva.');
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final group = _group;
    final field = group.field;
    final textTheme = Theme.of(context).textTheme;
    final photos = field?.venue.photos ?? const [];
    final venueCancellations = group.reservations.where((item) => item.isCancelled && item.cancelledByVenue).toList();

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.of(context).pop(_changed);
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Detalle de la reserva')),
        body: ListView(
          children: [
            if (photos.isNotEmpty)
              SizedBox(
                height: 180,
                child: Image.network(
                  photos.first,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const ColoredBox(color: Colors.black12),
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(group.venueName ?? 'Cancha', style: textTheme.headlineSmall),
                      ),
                      ReservationStatusChip.group(group),
                    ],
                  ),
                  if (field != null) Text(field.venue.address),
                  for (final cancelled in venueCancellations) ...[
                    const SizedBox(height: 12),
                    _VenueCancellationNotice(reservation: cancelled, partial: !group.isCancelled),
                  ],
                  const SizedBox(height: 16),
                  Text(
                    group.reservations.length == 1 ? 'Cancha reservada' : 'Canchas reservadas',
                    style: textTheme.titleSmall,
                  ),
                  for (final reservation in group.reservations) _RangeRow(reservation: reservation),
                  const Divider(),
                  _Row(label: 'Referencia', value: group.reference),
                  _Row(label: 'Horas', value: group.hours == 1 ? '1 hora' : '${group.hours} horas'),
                  _Row(
                    label: group.isPaid ? 'Total pagado' : 'Total',
                    value: formatBs(group.amount),
                    emphasize: true,
                  ),
                  const SizedBox(height: 24),
                  if (_canCreateMatch || (group.matchId != null && widget.matchApiService != null)) ...[
                    _MatchCard(
                      matchId: group.matchId,
                      onCreate: _busy ? null : _createMatch,
                      onOpen: group.matchId == null ? null : () => _openMatch(group.matchId!),
                    ),
                    const SizedBox(height: 16),
                  ],
                  if (_canPay) ...[
                    FilledButton.icon(
                      onPressed: _busy ? null : _pay,
                      icon: const Icon(Icons.qr_code_2),
                      label: Text('Completar pago (simulado) · ${formatBs(group.amount)}'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: _busy ? null : _cancel,
                      child: const Text('Cancelar reserva'),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

CourtReservationModel _withField(CourtReservationModel item, CourtFieldModel? venueField, {int? matchId}) {
  // The pay response does not load venue photos; reuse the venue from the list.
  if (venueField == null) return item;
  return CourtReservationModel(
    id: item.id,
    bookingCode: item.bookingCode,
    matchId: item.matchId ?? matchId,
    date: item.date,
    startTime: item.startTime,
    endTime: item.endTime,
    hours: item.hours,
    amount: item.amount,
    status: item.status,
    sportName: item.sportName,
    fieldName: item.fieldName,
    venueName: item.venueName ?? venueField.venue.name,
    paymentReference: item.paymentReference,
    paymentExpiresAt: item.paymentExpiresAt,
    field: venueField,
    cancellationReason: item.cancellationReason,
    cancelledByVenue: item.cancelledByVenue,
    wasPaid: item.wasPaid,
    refunded: item.refunded,
  );
}

/// The match played on this booking: "Crear cancha" or, once created, "Ver cancha creada".
class _MatchCard extends StatelessWidget {
  const _MatchCard({required this.matchId, required this.onCreate, required this.onOpen});

  final int? matchId;
  final VoidCallback? onCreate;
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final created = matchId != null;
    return Card(
      margin: EdgeInsets.zero,
      color: created ? colors.secondaryContainer : colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(created ? Icons.sports_score_rounded : Icons.groups_rounded),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    created ? 'Ya creaste la cancha de esta reserva' : '¿Juegas con más gente?',
                    style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              created
                  ? 'Mira quién se unió, comparte el partido o agrega jugadores.'
                  : 'Crea la cancha con esta reserva: el centro, las canchas y el horario ya quedan completos. '
                      'Así otros jugadores pueden unirse.',
              style: textTheme.bodySmall,
            ),
            const SizedBox(height: 12),
            created
                ? FilledButton.icon(
                    onPressed: onOpen,
                    icon: const Icon(Icons.visibility_outlined),
                    label: const Text('Ver cancha creada'),
                  )
                : FilledButton.icon(
                    onPressed: onCreate,
                    icon: const Icon(Icons.add_circle_outline),
                    label: const Text('Crear cancha'),
                  ),
          ],
        ),
      ),
    );
  }
}

/// "Cancha 2 · Wally / jueves 1 de octubre · 17:00–19:00 (2 h) · Bs 80".
class _RangeRow extends StatelessWidget {
  const _RangeRow({required this.reservation});

  final CourtReservationModel reservation;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final cancelled = reservation.isCancelled;
    final decoration = cancelled ? TextDecoration.lineThrough : null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  [reservation.fieldName, reservation.sportName].whereType<String>().join(' · '),
                  style: TextStyle(fontWeight: FontWeight.w600, decoration: decoration),
                ),
                Text(
                  '${formatReservationDate(reservation.startsAt)} · '
                  '${reservation.startTime}–${reservation.endTime} (${reservation.hours} h)',
                  style: textTheme.bodySmall?.copyWith(decoration: decoration),
                ),
                if (reservation.lightingAmount > 0)
                  Text(
                    '+ Luz nocturna · ${formatBs(reservation.lightingAmount)}',
                    style: textTheme.bodySmall?.copyWith(decoration: decoration),
                  ),
                if (reservation.airConditioning)
                  Text(
                    '+ Aire acondicionado · ${formatBs(reservation.airConditioningAmount)}',
                    style: textTheme.bodySmall?.copyWith(decoration: decoration),
                  ),
                for (final rental in reservation.rentals)
                  Text(
                    '+ ${rental.quantity} × ${rental.name} · ${formatBs(rental.amount)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodySmall?.copyWith(decoration: decoration),
                  ),
                if (cancelled)
                  Text('Anulada', style: textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.error)),
              ],
            ),
          ),
          Text(
            formatBs(reservation.amount),
            style: TextStyle(fontWeight: FontWeight.w600, decoration: decoration),
          ),
        ],
      ),
    );
  }
}

class _VenueCancellationNotice extends StatelessWidget {
  const _VenueCancellationNotice({required this.reservation, required this.partial});

  final CourtReservationModel reservation;

  /// Only this range was cancelled; the rest of the booking stays.
  final bool partial;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: colors.errorContainer, borderRadius: BorderRadius.circular(8)),
      child: Text(
        [
          partial
              ? 'El centro deportivo anuló ${reservation.fieldName ?? 'una cancha'} '
                  '${reservation.startTime}–${reservation.endTime}.'
              : 'El centro deportivo anuló esta reserva.',
          if (reservation.cancellationReason != null) 'Motivo: ${reservation.cancellationReason}',
          if (reservation.wasPaid)
            reservation.refunded
                ? 'Ya te devolvieron ${formatBs(reservation.amount)}.'
                : 'El centro deportivo te devolverá ${formatBs(reservation.amount)}.',
        ].join('\n'),
        style: TextStyle(color: colors.onErrorContainer),
      ),
    );
  }
}

class ReservationStatusChip extends StatelessWidget {
  const ReservationStatusChip({
    required this.cancelled,
    required this.upcoming,
    required this.paid,
    super.key,
  });

  ReservationStatusChip.group(ReservationGroup group, {Key? key})
      : this(cancelled: group.isCancelled, upcoming: group.isUpcoming, paid: group.isPaid, key: key);

  final bool cancelled;
  final bool upcoming;
  final bool paid;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final (label, background, foreground) = switch (this) {
      _ when cancelled => ('Anulada', colors.errorContainer, colors.onErrorContainer),
      _ when !upcoming => ('Jugada', colors.surfaceContainerHighest, colors.onSurfaceVariant),
      _ when paid => ('Confirmada', Colors.green.shade100, Colors.green.shade900),
      _ => ('Pago pendiente', colors.tertiaryContainer, colors.onTertiaryContainer),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
      child: Text(
        label,
        style: TextStyle(color: foreground, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.emphasize = false});

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(fontWeight: FontWeight.w600, fontSize: emphasize ? 18 : null),
            ),
          ),
        ],
      ),
    );
  }
}
