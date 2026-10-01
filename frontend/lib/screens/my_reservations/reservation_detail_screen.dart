import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../services/court_api_service.dart';
import '../reserve_court/court_payment_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';

/// Detail of one of the user's reservations. Pops `true` when it was paid or cancelled here.
class ReservationDetailScreen extends StatefulWidget {
  const ReservationDetailScreen({
    required this.reservation,
    required this.courtApiService,
    super.key,
  });

  final CourtReservationModel reservation;
  final CourtApiService courtApiService;

  @override
  State<ReservationDetailScreen> createState() => _ReservationDetailScreenState();
}

class _ReservationDetailScreenState extends State<ReservationDetailScreen> {
  late CourtReservationModel _reservation = widget.reservation;
  bool _busy = false;
  bool _changed = false;

  bool get _canPay =>
      _reservation.isPendingPayment &&
      _reservation.isUpcoming &&
      (_reservation.paymentExpiresAt?.isAfter(DateTime.now()) ?? true);

  Future<void> _pay() async {
    setState(() => _busy = true);
    try {
      final paid = await widget.courtApiService.pay(_reservation.id);
      if (!mounted) return;
      setState(() {
        _reservation = paid;
        _changed = true;
      });
      _snack('Pago confirmado. ¡Tu cancha está reservada!');
    } catch (error) {
      _snack(error is CourtApiException ? error.message : 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cancelar la reserva?'),
        content: const Text('El horario quedará libre para otros.'),
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
      await widget.courtApiService.cancelReservation(_reservation.id);
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
    final reservation = _reservation;
    final field = reservation.field;
    final textTheme = Theme.of(context).textTheme;
    final photos = field?.venue.photos ?? const [];

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
                        child: Text(
                          reservation.venueName ?? 'Cancha',
                          style: textTheme.headlineSmall,
                        ),
                      ),
                      ReservationStatusChip(reservation: reservation),
                    ],
                  ),
                  if (field != null) Text(field.venue.address),
                  const SizedBox(height: 16),
                  _Row(label: 'Cancha', value: reservation.fieldName ?? '-'),
                  _Row(label: 'Deporte', value: reservation.sportName ?? '-'),
                  _Row(label: 'Fecha', value: formatReservationDate(reservation.startsAt)),
                  _Row(
                    label: 'Horario',
                    value: '${reservation.startTime} – ${reservation.endTime}',
                  ),
                  _Row(
                    label: 'Horas',
                    value: reservation.hours == 1 ? '1 hora' : '${reservation.hours} horas',
                  ),
                  if (field != null)
                    _Row(label: 'Precio por hora', value: formatBs(field.pricePerHour)),
                  if (reservation.paymentReference != null)
                    _Row(label: 'Referencia', value: reservation.paymentReference!),
                  const Divider(),
                  _Row(
                    label: reservation.isPaid ? 'Total pagado' : 'Total',
                    value: formatBs(reservation.amount),
                    emphasize: true,
                  ),
                  const SizedBox(height: 24),
                  if (_canPay) ...[
                    FilledButton.icon(
                      onPressed: _busy ? null : _pay,
                      icon: const Icon(Icons.qr_code_2),
                      label: const Text('Completar pago (simulado)'),
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

class ReservationStatusChip extends StatelessWidget {
  const ReservationStatusChip({required this.reservation, super.key});

  final CourtReservationModel reservation;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final (label, background, foreground) = switch (reservation) {
      _ when !reservation.isUpcoming => ('Jugada', colors.surfaceContainerHighest, colors.onSurfaceVariant),
      _ when reservation.isPaid => ('Confirmada', Colors.green.shade100, Colors.green.shade900),
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
