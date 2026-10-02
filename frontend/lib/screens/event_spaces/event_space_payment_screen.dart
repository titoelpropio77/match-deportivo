import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../../services/event_space_api_service.dart';
import '../reserve_court/court_payment_screen.dart' show formatReservationDate;
import '../reserve_court/reserve_courts_screen.dart';
import '../widgets/fake_qr.dart';

/// What the user picked on [EventSpaceDetailScreen].
class EventBookingRequest {
  const EventBookingRequest({
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.hours,
    required this.guests,
    this.eventType,
    this.notes,
  });

  final DateTime date;
  final String startTime;
  final String endTime;
  final int hours;
  final int guests;
  final String? eventType;
  final String? notes;
}

/// Summary and simulated QR payment of an event space. "Pagar con QR" holds the hours as
/// pending payment; [reservation] resumes a pending one from "Mis eventos". Pops `true` once paid.
class EventSpacePaymentScreen extends StatefulWidget {
  const EventSpacePaymentScreen({
    required this.space,
    required this.eventSpaceApiService,
    this.request,
    this.reservation,
    super.key,
  }) : assert(request != null || reservation != null);

  final EventSpaceModel space;
  final EventBookingRequest? request;
  final EventSpaceReservationModel? reservation;
  final EventSpaceApiService eventSpaceApiService;

  @override
  State<EventSpacePaymentScreen> createState() => _EventSpacePaymentScreenState();
}

class _EventSpacePaymentScreenState extends State<EventSpacePaymentScreen> {
  late EventSpaceReservationModel? _reservation = widget.reservation;
  bool _busy = false;
  Timer? _timer;
  Duration _remaining = Duration.zero;

  bool get _expired => _reservation?.paymentExpiresAt != null && _remaining <= Duration.zero;

  @override
  void initState() {
    super.initState();
    if (_reservation != null) _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  DateTime get _date => widget.request?.date ?? _reservation!.date;
  String get _startTime => widget.request?.startTime ?? _reservation!.startTime;
  String get _endTime => widget.request?.endTime ?? _reservation!.endTime;
  int get _hours => widget.request?.hours ?? _reservation!.hours;
  int get _guests => widget.request?.guests ?? _reservation!.guests;
  double get _amount => _reservation?.amount ?? widget.space.pricePerHour * _hours;

  String? get _eventLabel {
    final key = widget.request?.eventType;
    if (key != null) return eventKinds.where((kind) => kind.key == key).firstOrNull?.label;
    return _reservation?.eventType?.label;
  }

  Future<void> _generateQr() async {
    final request = widget.request!;
    setState(() => _busy = true);
    try {
      final reservation = await widget.eventSpaceApiService.reserve(
        spaceId: widget.space.id,
        date: request.date,
        startTime: request.startTime,
        hours: request.hours,
        guests: request.guests,
        eventType: request.eventType,
        notes: request.notes,
      );
      if (!mounted) return;
      setState(() => _reservation = reservation);
      _startCountdown();
    } catch (error) {
      _showError(error, 'No pudimos registrar la reserva.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _startCountdown() {
    final expiresAt = _reservation?.paymentExpiresAt;
    if (expiresAt == null) return;
    void tick() {
      final remaining = expiresAt.difference(DateTime.now());
      setState(() => _remaining = remaining.isNegative ? Duration.zero : remaining);
      if (remaining <= Duration.zero) _timer?.cancel();
    }

    tick();
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _simulatePayment() async {
    final reservation = _reservation;
    if (reservation == null) return;
    setState(() => _busy = true);
    try {
      final paid = await widget.eventSpaceApiService.pay(reservation.id);
      if (!mounted) return;
      _timer?.cancel();
      await Navigator.of(context).push<void>(
        MaterialPageRoute(
          builder: (_) => EventReservationSuccessScreen(space: widget.space, reservation: paid),
        ),
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      _showError(error, 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Leaving with an unpaid reservation releases its hours.
  Future<void> _leave() async {
    final reservation = _reservation;
    if (reservation == null || _expired) {
      Navigator.of(context).pop();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cancelar la reserva?'),
        content: const Text('Todavía no pagaste. Si sales, el horario quedará libre para otros.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Seguir pagando')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Cancelar reserva')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.eventSpaceApiService.cancel(reservation.id);
    } catch (_) {
      // The hold expires on its own after the payment window.
    }
    if (mounted) Navigator.of(context).pop();
  }

  void _showError(Object error, String fallback) {
    if (!mounted) return;
    final message = error is EventSpaceApiException ? error.message : fallback;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final reservation = _reservation;
    final textTheme = Theme.of(context).textTheme;
    final venue = widget.space.venue;
    final minutes = _remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');

    return PopScope(
      canPop: reservation == null || _expired,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Tu evento')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(widget.space.name, style: textTheme.titleMedium),
                    if (venue != null) Text(venue.name, style: textTheme.bodySmall),
                    const SizedBox(height: 8),
                    _Row(label: 'Fecha', value: formatReservationDate(_date)),
                    _Row(label: 'Horario', value: '$_startTime–$_endTime ($_hours h)'),
                    _Row(label: 'Personas', value: '$_guests'),
                    if (_eventLabel != null) _Row(label: 'Evento', value: _eventLabel!),
                    const Divider(),
                    _Row(label: 'Total', value: formatBs(_amount), emphasize: true),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            if (reservation == null)
              FilledButton.icon(
                onPressed: _busy ? null : _generateQr,
                icon: _busy ? const _ButtonSpinner() : const Icon(Icons.qr_code_2),
                label: Text('Pagar con QR · ${formatBs(_amount)}'),
              )
            else
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Text('Escanea el QR para pagar', style: textTheme.titleMedium),
                      const SizedBox(height: 4),
                      Text(formatBs(reservation.amount), style: textTheme.headlineMedium),
                      const SizedBox(height: 12),
                      Opacity(
                        opacity: _expired ? 0.2 : 1,
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          color: Colors.white,
                          child: SizedBox.square(
                            dimension: 200,
                            child: CustomPaint(painter: FakeQrPainter('${reservation.code}|${reservation.amount}')),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text('Referencia: ${reservation.code}', style: textTheme.bodySmall),
                      const SizedBox(height: 4),
                      Text(
                        _expired
                            ? 'El QR expiró y el horario fue liberado.'
                            : 'Tu horario queda apartado por $minutes:$seconds',
                        style: textTheme.bodyMedium?.copyWith(
                          color: _expired ? Theme.of(context).colorScheme.error : null,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.secondaryContainer,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Text(
                          'Modo prueba: el pago por QR todavía no está conectado al banco. '
                          'Usa "Simular pago" para confirmar la reserva.',
                          textAlign: TextAlign.center,
                        ),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: _busy || _expired ? null : _simulatePayment,
                          icon: _busy ? const _ButtonSpinner() : const Icon(Icons.check_circle_outline),
                          label: const Text('Simular pago'),
                        ),
                      ),
                      if (!_expired)
                        TextButton(onPressed: _busy ? null : _leave, child: const Text('Cancelar reserva')),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class EventReservationSuccessScreen extends StatelessWidget {
  const EventReservationSuccessScreen({required this.space, required this.reservation, super.key});

  final EventSpaceModel space;
  final EventSpaceReservationModel reservation;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Reserva confirmada'), automaticallyImplyLeading: false),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Icon(Icons.celebration, size: 72, color: colors.tertiary),
          const SizedBox(height: 12),
          Text('¡Todo listo para tu evento!', textAlign: TextAlign.center, style: textTheme.headlineSmall),
          const SizedBox(height: 4),
          Text('Pago recibido · ${reservation.code}', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          Text(space.name, style: textTheme.titleMedium),
          if (space.venue != null) Text(space.venue!.name, style: textTheme.bodySmall),
          const SizedBox(height: 8),
          _Row(label: 'Fecha', value: formatReservationDate(reservation.date)),
          _Row(label: 'Horario', value: '${reservation.startTime}–${reservation.endTime}'),
          _Row(label: 'Personas', value: '${reservation.guests}'),
          const Divider(),
          _Row(label: 'Total pagado', value: formatBs(reservation.amount), emphasize: true),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Listo')),
        ],
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

class _ButtonSpinner extends StatelessWidget {
  const _ButtonSpinner();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2));
  }
}
