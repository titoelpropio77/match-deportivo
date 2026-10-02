import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/tournament_model.dart';
import '../../services/tournament_api_service.dart';
import '../widgets/fake_qr.dart';
import 'tournament_format.dart';

/// Entry fee payment with the simulated QR. Pops `true` once paid.
class TournamentPaymentScreen extends StatefulWidget {
  const TournamentPaymentScreen({
    required this.tournament,
    required this.registration,
    required this.tournamentApiService,
    super.key,
  });

  final TournamentModel tournament;
  final TournamentRegistrationModel registration;
  final TournamentApiService tournamentApiService;

  @override
  State<TournamentPaymentScreen> createState() => _TournamentPaymentScreenState();
}

class _TournamentPaymentScreenState extends State<TournamentPaymentScreen> {
  Timer? _timer;
  Duration _remaining = Duration.zero;
  bool _busy = false;

  bool get _expired => widget.registration.paymentExpiresAt != null && _remaining <= Duration.zero;

  @override
  void initState() {
    super.initState();
    final expiresAt = widget.registration.paymentExpiresAt;
    if (expiresAt == null) return;
    void tick() {
      final remaining = expiresAt.difference(DateTime.now());
      setState(() => _remaining = remaining.isNegative ? Duration.zero : remaining);
      if (remaining <= Duration.zero) _timer?.cancel();
    }

    tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _simulatePayment() async {
    setState(() => _busy = true);
    try {
      await widget.tournamentApiService.pay(widget.registration.id);
      if (!mounted) return;
      _timer?.cancel();
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          icon: Icon(Icons.verified_rounded, color: Colors.green.shade600, size: 48),
          title: const Text('¡Equipo inscrito!'),
          content: Text(
            '${widget.registration.team?.name ?? 'Tu equipo'} ya está confirmado en ${widget.tournament.name}. '
            'Revisa el fixture cuando se publique.',
          ),
          actions: [FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Listo'))],
        ),
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error is TournamentApiException ? error.message : 'No pudimos confirmar el pago.')),
      );
    }
  }

  Future<void> _cancel() async {
    try {
      await widget.tournamentApiService.cancel(widget.registration.id);
    } catch (_) {
      // The spot is released anyway when the payment window ends.
    }
    if (mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final registration = widget.registration;
    final minutes = _remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');

    return Scaffold(
      appBar: AppBar(title: const Text('Pago de inscripción')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.emoji_events_outlined),
              title: Text(widget.tournament.name),
              subtitle: Text('Equipo: ${registration.team?.name ?? '-'}'),
              trailing: Text(bsAmount(registration.amount), style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Text('Escanea el QR para pagar', style: textTheme.titleMedium),
                  Text(bsAmount(registration.amount), style: textTheme.headlineMedium),
                  const SizedBox(height: 12),
                  Opacity(
                    opacity: _expired ? 0.2 : 1,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      color: Colors.white,
                      child: SizedBox.square(
                        dimension: 200,
                        child: CustomPaint(painter: FakeQrPainter('${registration.paymentReference}|${registration.amount}')),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text('Referencia: ${registration.paymentReference}', style: textTheme.bodySmall),
                  const SizedBox(height: 4),
                  Text(
                    _expired ? 'El QR expiró y el cupo se liberó.' : 'Tu cupo queda apartado por $minutes:$seconds',
                    style: TextStyle(color: _expired ? Theme.of(context).colorScheme.error : null),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.secondaryContainer,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Modo prueba: el pago por QR todavía no está conectado al banco. Usa "Simular pago".',
                      textAlign: TextAlign.center,
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: _busy || _expired ? null : _simulatePayment,
                      icon: const Icon(Icons.check_circle_outline),
                      label: const Text('Simular pago'),
                    ),
                  ),
                  if (!_expired) TextButton(onPressed: _busy ? null : _cancel, child: const Text('Cancelar inscripción')),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
