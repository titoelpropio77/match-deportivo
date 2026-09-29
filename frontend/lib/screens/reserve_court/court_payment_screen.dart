import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/sport_model.dart';
import '../../services/court_api_service.dart';
import 'reserve_courts_screen.dart';

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
  bool _paying = false;

  double get _amount => widget.field.pricePerHour * widget.hours;

  Future<void> _payWithQr() async {
    setState(() => _paying = true);
    try {
      await widget.courtApiService.reserve(
        fieldId: widget.field.id,
        sportId: widget.sport.id,
        date: widget.date,
        startTime: widget.startTime,
        hours: widget.hours,
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Pago por QR'),
          content: const Text(
            'El pago por QR todavía no está conectado. Tu reserva quedó registrada y el horario ya figura como ocupado.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Entendido'),
            ),
          ],
        ),
      );
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (error) {
      if (!mounted) return;
      final message = error is CourtApiException
          ? error.message
          : 'No pudimos registrar la reserva.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final field = widget.field;
    return Scaffold(
      appBar: AppBar(title: const Text('Detalle de pago')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Estás pagando', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          _Row(label: 'Lugar', value: field.venue.name),
          _Row(label: 'Cancha', value: field.name),
          _Row(label: 'Deporte', value: widget.sport.name),
          _Row(label: 'Fecha', value: _formatDate(widget.date)),
          _Row(label: 'Horario', value: '${widget.startTime} – ${widget.endTime}'),
          _Row(label: 'Horas', value: '${widget.hours}'),
          _Row(label: 'Precio por hora', value: formatBs(field.pricePerHour)),
          const Divider(),
          _Row(label: 'Total', value: formatBs(_amount)),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: _paying ? null : _payWithQr,
            icon: _paying
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.qr_code_2),
            label: Text('Pago por QR · ${formatBs(_amount)}'),
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

String _formatDate(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}
