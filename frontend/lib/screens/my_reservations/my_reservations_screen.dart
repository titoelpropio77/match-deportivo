import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../services/court_api_service.dart';
import '../reserve_court/court_payment_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import 'reservation_detail_screen.dart';

/// Courts the user booked from "Reservar cancha": upcoming first, then past ones.
class MyReservationsScreen extends StatefulWidget {
  const MyReservationsScreen({required this.courtApiService, super.key});

  final CourtApiService courtApiService;

  @override
  State<MyReservationsScreen> createState() => _MyReservationsScreenState();
}

class _MyReservationsScreenState extends State<MyReservationsScreen> {
  List<CourtReservationModel> _reservations = const [];
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
      final reservations = await widget.courtApiService.myReservations();
      if (!mounted) return;
      setState(() {
        _reservations = reservations;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is CourtApiException
            ? error.message
            : 'No pudimos cargar tus reservas.';
        _loading = false;
      });
    }
  }

  Future<void> _open(CourtReservationModel reservation) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ReservationDetailScreen(
          reservation: reservation,
          courtApiService: widget.courtApiService,
        ),
      ),
    );
    if (changed == true && mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis reservas')),
      body: RefreshIndicator(onRefresh: _load, child: _buildBody()),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 120),
          Center(child: Text(_error!)),
          const SizedBox(height: 12),
          Center(child: FilledButton(onPressed: _load, child: const Text('Reintentar'))),
        ],
      );
    }
    if (_reservations.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 120),
          Center(child: Text('Todavía no reservaste ninguna cancha.')),
        ],
      );
    }

    final upcoming = _reservations.where((item) => item.isUpcoming).toList();
    final past = _reservations.where((item) => !item.isUpcoming).toList();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (upcoming.isNotEmpty) ...[
          const _SectionTitle('Próximas'),
          for (final reservation in upcoming)
            _ReservationCard(reservation: reservation, onTap: () => _open(reservation)),
        ],
        if (past.isNotEmpty) ...[
          const _SectionTitle('Anteriores'),
          for (final reservation in past)
            _ReservationCard(reservation: reservation, onTap: () => _open(reservation)),
        ],
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 8, 4, 8),
      child: Text(text, style: Theme.of(context).textTheme.titleMedium),
    );
  }
}

class _ReservationCard extends StatelessWidget {
  const _ReservationCard({required this.reservation, required this.onTap});

  final CourtReservationModel reservation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final photos = reservation.field?.venue.photos ?? const [];

    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        child: Opacity(
          opacity: reservation.isUpcoming ? 1 : 0.6,
          child: Row(
            children: [
              SizedBox(
                width: 96,
                height: 104,
                child: photos.isEmpty
                    ? ColoredBox(
                        color: colors.primaryContainer,
                        child: Icon(Icons.stadium_outlined, color: colors.onPrimaryContainer),
                      )
                    : Image.network(
                        photos.first,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => ColoredBox(
                          color: colors.primaryContainer,
                          child: Icon(Icons.stadium_outlined, color: colors.onPrimaryContainer),
                        ),
                      ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        reservation.venueName ?? 'Cancha',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        [reservation.fieldName, reservation.sportName].whereType<String>().join(' · '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${formatReservationDate(reservation.startsAt)} · ${reservation.startTime}–${reservation.endTime}',
                        style: textTheme.bodySmall,
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          ReservationStatusChip(reservation: reservation),
                          const Spacer(),
                          Text(formatBs(reservation.amount), style: textTheme.titleSmall),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
              const SizedBox(width: 4),
            ],
          ),
        ),
      ),
    );
  }
}
