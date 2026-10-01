import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/sport_model.dart';
import '../../services/court_api_service.dart';
import 'reserve_courts_screen.dart';

/// Reservation summary and QR payment. The QR is simulated: the reservation is
/// held as `pending_payment` and "Simular pago" confirms it on the backend.
class CourtPaymentScreen extends StatefulWidget {
  const CourtPaymentScreen({
    required this.field,
    required this.sport,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.hours,
    required this.courtApiService,
    super.key,
  });

  final CourtFieldModel field;
  final SportModel sport;
  final DateTime date;
  final String startTime;
  final String endTime;
  final int hours;
  final CourtApiService courtApiService;

  @override
  State<CourtPaymentScreen> createState() => _CourtPaymentScreenState();
}

class _CourtPaymentScreenState extends State<CourtPaymentScreen> {
  CourtReservationModel? _reservation;
  bool _busy = false;
  Timer? _timer;
  Duration _remaining = Duration.zero;

  double get _amount => widget.field.pricePerHour * widget.hours;

  bool get _expired => _reservation?.paymentExpiresAt != null && _remaining <= Duration.zero;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _generateQr() async {
    setState(() => _busy = true);
    try {
      final reservation = await widget.courtApiService.reserve(
        fieldId: widget.field.id,
        sportId: widget.sport.id,
        date: widget.date,
        startTime: widget.startTime,
        hours: widget.hours,
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
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _simulatePayment() async {
    final reservation = _reservation;
    if (reservation == null) return;
    setState(() => _busy = true);
    try {
      final paid = await widget.courtApiService.pay(reservation.id);
      if (!mounted) return;
      _timer?.cancel();
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => CourtReservationSuccessScreen(
            reservation: paid,
            venueName: widget.field.venue.name,
            fieldName: widget.field.name,
            sportName: widget.sport.name,
          ),
        ),
      );
    } catch (error) {
      _showError(error, 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Leaving with an unpaid reservation releases the hour.
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
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Seguir pagando'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Cancelar reserva'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.courtApiService.cancelReservation(reservation.id);
    } catch (_) {
      // The hold expires on its own after the payment window.
    }
    if (mounted) Navigator.of(context).pop();
  }

  void _showError(Object error, String fallback) {
    if (!mounted) return;
    final message = error is CourtApiException ? error.message : fallback;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final field = widget.field;
    final reservation = _reservation;
    return PopScope(
      canPop: reservation == null || _expired,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Detalle de la reserva')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Resumen', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    _Row(label: 'Centro deportivo', value: field.venue.name),
                    _Row(label: 'Cancha', value: field.name),
                    _Row(label: 'Deporte', value: widget.sport.name),
                    _Row(label: 'Fecha', value: formatReservationDate(widget.date)),
                    _Row(label: 'Horario', value: '${widget.startTime} – ${widget.endTime}'),
                    _Row(label: 'Horas', value: '${widget.hours}'),
                    _Row(label: 'Precio por hora', value: formatBs(field.pricePerHour)),
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
              _QrPanel(
                reservation: reservation,
                remaining: _remaining,
                expired: _expired,
                busy: _busy,
                onSimulatePayment: _simulatePayment,
                onCancel: _leave,
              ),
          ],
        ),
      ),
    );
  }
}

class _QrPanel extends StatelessWidget {
  const _QrPanel({
    required this.reservation,
    required this.remaining,
    required this.expired,
    required this.busy,
    required this.onSimulatePayment,
    required this.onCancel,
  });

  final CourtReservationModel reservation;
  final Duration remaining;
  final bool expired;
  final bool busy;
  final VoidCallback onSimulatePayment;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final minutes = remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (remaining.inSeconds % 60).toString().padLeft(2, '0');
    final reference = reservation.paymentReference ?? 'MD-${reservation.id}';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Text('Escanea el QR para pagar', style: textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(formatBs(reservation.amount), style: textTheme.headlineMedium),
            const SizedBox(height: 12),
            Opacity(
              opacity: expired ? 0.2 : 1,
              child: Container(
                padding: const EdgeInsets.all(12),
                color: Colors.white,
                child: SizedBox.square(
                  dimension: 200,
                  child: CustomPaint(painter: _FakeQrPainter('$reference|${reservation.amount}')),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text('Referencia: $reference', style: textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(
              expired
                  ? 'El QR expiró y el horario fue liberado.'
                  : 'El horario queda apartado por $minutes:$seconds',
              style: textTheme.bodyMedium?.copyWith(
                color: expired ? Theme.of(context).colorScheme.error : null,
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
                onPressed: busy || expired ? null : onSimulatePayment,
                icon: busy ? const _ButtonSpinner() : const Icon(Icons.check_circle_outline),
                label: const Text('Simular pago'),
              ),
            ),
            if (!expired)
              TextButton(onPressed: busy ? null : onCancel, child: const Text('Cancelar reserva')),
          ],
        ),
      ),
    );
  }
}

class CourtReservationSuccessScreen extends StatelessWidget {
  const CourtReservationSuccessScreen({
    required this.reservation,
    required this.venueName,
    required this.fieldName,
    required this.sportName,
    super.key,
  });

  final CourtReservationModel reservation;
  final String venueName;
  final String fieldName;
  final String sportName;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final date = DateTime.tryParse(reservation.date);
    return Scaffold(
      appBar: AppBar(title: const Text('Reserva confirmada'), automaticallyImplyLeading: false),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Icon(Icons.check_circle, size: 72, color: Colors.green.shade600),
          const SizedBox(height: 12),
          Text('¡Cancha reservada!', textAlign: TextAlign.center, style: textTheme.headlineSmall),
          const SizedBox(height: 4),
          Text(
            'Pago recibido · ${reservation.paymentReference ?? ''}',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          _Row(label: 'Centro deportivo', value: venueName),
          _Row(label: 'Cancha', value: fieldName),
          _Row(label: 'Deporte', value: sportName),
          _Row(
            label: 'Fecha',
            value: date == null ? reservation.date : formatReservationDate(date),
          ),
          _Row(label: 'Horario', value: '${reservation.startTime} – ${reservation.endTime}'),
          const Divider(),
          _Row(label: 'Total pagado', value: formatBs(reservation.amount), emphasize: true),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
            child: const Text('Volver al inicio'),
          ),
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
              style: TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: emphasize ? 18 : null,
              ),
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
    return const SizedBox(
      width: 18,
      height: 18,
      child: CircularProgressIndicator(strokeWidth: 2),
    );
  }
}

/// Draws a QR-looking grid derived from [data]. Placeholder until the bank QR is integrated.
class _FakeQrPainter extends CustomPainter {
  _FakeQrPainter(this.data);

  final String data;

  static const _cells = 25;

  @override
  void paint(Canvas canvas, Size size) {
    final cell = size.width / _cells;
    final paint = Paint()..color = Colors.black;
    var seed = data.codeUnits.fold<int>(17, (hash, unit) => (hash * 31 + unit) & 0x7fffffff);

    bool inFinder(int x, int y) {
      bool near(int ox, int oy) => x >= ox && x < ox + 8 && y >= oy && y < oy + 8;
      return near(0, 0) || near(_cells - 8, 0) || near(0, _cells - 8);
    }

    for (var y = 0; y < _cells; y++) {
      for (var x = 0; x < _cells; x++) {
        if (inFinder(x, y)) continue;
        seed = (seed * 1103515245 + 12345) & 0x7fffffff;
        if (seed % 2 == 0) {
          canvas.drawRect(Rect.fromLTWH(x * cell, y * cell, cell, cell), paint);
        }
      }
    }

    void finder(int ox, int oy) {
      final outer = Rect.fromLTWH(ox * cell, oy * cell, cell * 7, cell * 7);
      canvas.drawRect(outer, paint);
      canvas.drawRect(outer.deflate(cell), Paint()..color = Colors.white);
      canvas.drawRect(outer.deflate(cell * 2), paint);
    }

    finder(0, 0);
    finder(_cells - 7, 0);
    finder(0, _cells - 7);
  }

  @override
  bool shouldRepaint(_FakeQrPainter oldDelegate) => oldDelegate.data != data;
}

String formatReservationDate(DateTime date) {
  const days = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
  const months = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
  ];
  return '${days[date.weekday - 1]} ${date.day} de ${months[date.month - 1]}';
}
