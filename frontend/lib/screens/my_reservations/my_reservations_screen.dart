import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../reserve_court/court_payment_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import 'reservation_detail_screen.dart';

/// Courts the user booked from "Reservar cancha": upcoming first, then past ones.
class MyReservationsScreen extends StatefulWidget {
  const MyReservationsScreen({
    required this.courtApiService,
    this.matchApiService,
    this.currentUserId,
    this.title = 'Mis reservas',
    super.key,
  });

  final String title;

  final CourtApiService courtApiService;

  /// Enables "Crear cancha" / "Ver cancha creada" in the detail.
  final MatchApiService? matchApiService;
  final int? currentUserId;

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

  Future<void> _open(ReservationGroup group) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ReservationDetailScreen(
          group: group,
          courtApiService: widget.courtApiService,
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
    if (changed == true && mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
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

    // One card per booking: every court and hour range booked together.
    final groups = ReservationGroup.fromReservations(_reservations);
    final upcoming = groups.where((group) => group.isUpcoming).toList();
    final past = groups.where((group) => !group.isUpcoming).toList();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (upcoming.isNotEmpty) ...[
          const _SectionTitle('Próximas'),
          for (final group in upcoming) _ReservationCard(group: group, onTap: () => _open(group)),
        ],
        if (past.isNotEmpty) ...[
          const _SectionTitle('Anteriores'),
          for (final group in past) _ReservationCard(group: group, onTap: () => _open(group)),
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
  const _ReservationCard({required this.group, required this.onTap});

  final ReservationGroup group;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final photos = group.field?.venue.photos ?? const [];
    final shown = group.isCancelled ? group.reservations : group.active;
    final placeholder = ColoredBox(
      color: colors.primaryContainer,
      child: Icon(Icons.stadium_outlined, color: colors.onPrimaryContainer),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        child: Opacity(
          opacity: group.isUpcoming ? 1 : 0.6,
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 96,
                  child: photos.isEmpty
                      ? placeholder
                      : Image.network(photos.first, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          group.venueName ?? 'Cancha',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        Text(formatReservationDate(group.startsAt), style: textTheme.bodySmall),
                        const SizedBox(height: 4),
                        for (final reservation in shown)
                          Text(
                            '${reservation.fieldName ?? 'Cancha'} · ${reservation.startTime}–${reservation.endTime}'
                            '${reservation.date == group.first.date ? '' : ' (${formatReservationDate(reservation.startsAt)})'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            ReservationStatusChip.group(group),
                            const Spacer(),
                            Text(formatBs(group.amount), style: textTheme.titleSmall),
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
      ),
    );
  }
}
