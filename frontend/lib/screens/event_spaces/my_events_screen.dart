import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../../services/event_space_api_service.dart';
import '../reserve_court/court_payment_screen.dart' show formatReservationDate;
import '../reserve_court/reserve_courts_screen.dart';
import 'event_space_card.dart';
import 'event_space_payment_screen.dart';

/// Event space reservations of the user: upcoming first, then past ones and venue cancellations.
class MyEventsScreen extends StatefulWidget {
  const MyEventsScreen({required this.eventSpaceApiService, super.key});

  final EventSpaceApiService eventSpaceApiService;

  @override
  State<MyEventsScreen> createState() => _MyEventsScreenState();
}

class _MyEventsScreenState extends State<MyEventsScreen> {
  List<EventSpaceReservationModel> _reservations = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final reservations = await widget.eventSpaceApiService.myReservations();
      if (!mounted) return;
      setState(() {
        _reservations = reservations;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is EventSpaceApiException ? error.message : 'No pudimos cargar tus eventos.';
        _loading = false;
      });
    }
  }

  Future<void> _pay(EventSpaceReservationModel reservation) async {
    final space = reservation.space;
    if (space == null) return;
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EventSpacePaymentScreen(
          space: space,
          reservation: reservation,
          eventSpaceApiService: widget.eventSpaceApiService,
        ),
      ),
    );
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis eventos')),
      body: RefreshIndicator(onRefresh: _load, child: _body()),
    );
  }

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 64),
          Text(_error!, textAlign: TextAlign.center),
          Center(child: TextButton(onPressed: _load, child: const Text('Reintentar'))),
        ],
      );
    }
    if (_reservations.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 80),
          Icon(Icons.celebration_outlined, size: 48),
          SizedBox(height: 12),
          Text('Aún no reservaste espacios para eventos.', textAlign: TextAlign.center),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _reservations.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) => _EventReservationCard(
        reservation: _reservations[index],
        onPay: () => _pay(_reservations[index]),
      ),
    );
  }
}

class _EventReservationCard extends StatelessWidget {
  const _EventReservationCard({required this.reservation, required this.onPay});

  final EventSpaceReservationModel reservation;
  final VoidCallback onPay;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final space = reservation.space;
    final (statusLabel, statusColor) = switch (reservation.status) {
      'paid' => ('Pagado', Colors.green.shade700),
      'confirmed' => ('Confirmado · pagas en el local', colors.primary),
      'pending_payment' => ('Pago pendiente', Colors.orange.shade800),
      'cancelled' => (
          reservation.refundedAt != null ? 'Anulado por el centro · reembolsado' : 'Anulado por el centro',
          colors.error,
        ),
      _ => (reservation.status, colors.onSurfaceVariant),
    };
    final past = !reservation.isCancelled && !reservation.isUpcoming;

    return Opacity(
      opacity: past ? 0.6 : 1,
      child: Card(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: colors.outlineVariant),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (space != null) SizedBox(width: 96, height: 120, child: EventSpacePhoto(space: space)),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      space?.name ?? 'Espacio para eventos',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.titleSmall,
                    ),
                    if (space?.venue != null)
                      Text(
                        space!.venue!.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                      ),
                    const SizedBox(height: 6),
                    Text(
                      '${formatReservationDate(reservation.date)} · ${reservation.startTime}–${reservation.endTime}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall,
                    ),
                    Text(
                      '${reservation.guests} personas'
                      '${reservation.eventType == null ? '' : ' · ${reservation.eventType!.label}'}'
                      ' · ${formatBs(reservation.amount)}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      past ? 'Realizado' : statusLabel,
                      style: textTheme.labelMedium?.copyWith(color: past ? colors.onSurfaceVariant : statusColor),
                    ),
                    if (reservation.isCancelled && reservation.cancellationReason != null)
                      Text(
                        'Motivo: ${reservation.cancellationReason}',
                        style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                      ),
                    if (reservation.isPending && reservation.isUpcoming)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(onPressed: onPay, child: const Text('Pagar')),
                      ),
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
