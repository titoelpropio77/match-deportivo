import 'package:flutter/material.dart';

import '../../models/tournament_model.dart';
import '../../services/team_api_service.dart';
import '../../services/tournament_api_service.dart';
import 'tournament_detail_screen.dart';
import 'tournament_format.dart';

/// "Torneos": upcoming tournaments, the ones the user's teams play, and finished ones.
class TournamentsScreen extends StatelessWidget {
  const TournamentsScreen({
    required this.tournamentApiService,
    required this.teamApiService,
    required this.currentUserId,
    super.key,
  });

  final TournamentApiService tournamentApiService;
  final TeamApiService teamApiService;
  final int currentUserId;

  void _open(BuildContext context, int tournamentId) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TournamentDetailScreen(
          tournamentId: tournamentId,
          tournamentApiService: tournamentApiService,
          teamApiService: teamApiService,
          currentUserId: currentUserId,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Torneos'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Próximos'),
              Tab(text: 'Mis torneos'),
              Tab(text: 'Finalizados'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _TournamentList(
              load: () => tournamentApiService.list(),
              empty: 'No hay torneos próximos por ahora.',
              onOpen: (id) => _open(context, id),
            ),
            _MyTournaments(
              tournamentApiService: tournamentApiService,
              onOpen: (id) => _open(context, id),
            ),
            _TournamentList(
              load: () => tournamentApiService.list(scope: 'finished'),
              empty: 'Todavía no hay torneos finalizados.',
              onOpen: (id) => _open(context, id),
            ),
          ],
        ),
      ),
    );
  }
}

class _TournamentList extends StatefulWidget {
  const _TournamentList({required this.load, required this.empty, required this.onOpen});

  final Future<List<TournamentModel>> Function() load;
  final String empty;
  final ValueChanged<int> onOpen;

  @override
  State<_TournamentList> createState() => _TournamentListState();
}

class _TournamentListState extends State<_TournamentList> with AutomaticKeepAliveClientMixin {
  late Future<List<TournamentModel>> _future = widget.load();

  @override
  bool get wantKeepAlive => true;

  Future<void> _refresh() async {
    final future = widget.load();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<TournamentModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _Message(
              icon: Icons.cloud_off_outlined,
              text: snapshot.error is TournamentApiException
                  ? (snapshot.error as TournamentApiException).message
                  : 'No pudimos cargar los torneos.',
              onRetry: _refresh,
            );
          }
          final tournaments = snapshot.data ?? const [];
          if (tournaments.isEmpty) return _Message(icon: Icons.emoji_events_outlined, text: widget.empty);
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tournaments.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) => TournamentCard(
              tournament: tournaments[index],
              onTap: () => widget.onOpen(tournaments[index].id),
            ),
          );
        },
      ),
    );
  }
}

class _MyTournaments extends StatefulWidget {
  const _MyTournaments({required this.tournamentApiService, required this.onOpen});

  final TournamentApiService tournamentApiService;
  final ValueChanged<int> onOpen;

  @override
  State<_MyTournaments> createState() => _MyTournamentsState();
}

class _MyTournamentsState extends State<_MyTournaments> with AutomaticKeepAliveClientMixin {
  late Future<List<MyTournamentEntry>> _future = widget.tournamentApiService.mine();

  @override
  bool get wantKeepAlive => true;

  Future<void> _refresh() async {
    final future = widget.tournamentApiService.mine();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<MyTournamentEntry>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _Message(icon: Icons.cloud_off_outlined, text: 'No pudimos cargar tus torneos.', onRetry: _refresh);
          }
          final entries = snapshot.data ?? const [];
          if (entries.isEmpty) {
            return const _Message(
              icon: Icons.groups_outlined,
              text: 'Ninguno de tus equipos está inscrito en un torneo.\nEl capitán inscribe al equipo desde el detalle del torneo.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: entries.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final entry = entries[index];
              return TournamentCard(
                tournament: entry.tournament,
                registration: entry.registration,
                onTap: () => widget.onOpen(entry.tournament.id),
              );
            },
          );
        },
      ),
    );
  }
}

class TournamentCard extends StatelessWidget {
  const TournamentCard({required this.tournament, required this.onTap, this.registration, super.key});

  final TournamentModel tournament;
  final TournamentRegistrationModel? registration;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final cover = tournament.coverUrl;
    final placeholder = Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [colors.primary, colors.tertiary]),
      ),
      child: Center(child: Icon(Icons.emoji_events_rounded, size: 48, color: colors.onPrimary)),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                SizedBox(
                  height: 120,
                  width: double.infinity,
                  child: cover == null
                      ? placeholder
                      : Image.network(cover, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
                ),
                Positioned(top: 8, left: 8, child: TournamentStatusChip(tournament: tournament)),
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(16)),
                    child: Text(
                      tournament.isFree ? 'Gratis' : '${tournamentFee(tournament)} / equipo',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(tournament.name, style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  Text(
                    [tournament.sport?.name, tournament.formatLabel, tournament.gender.label].whereType<String>().join(' · '),
                    style: textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  _IconLine(icon: Icons.place_outlined, text: tournament.venueName ?? '-'),
                  _IconLine(
                    icon: Icons.calendar_month_outlined,
                    text: 'Empieza el ${tournamentDate(tournament.startsOn)}',
                  ),
                  _IconLine(
                    icon: Icons.groups_outlined,
                    text: '${tournament.teamsCount} de ${tournament.maxTeams} equipos'
                        '${tournament.acceptsRegistrations ? ' · quedan ${tournament.spotsLeft} cupos' : ''}',
                  ),
                  if (tournament.acceptsRegistrations)
                    _IconLine(
                      icon: Icons.timer_outlined,
                      text: 'Inscripciones hasta el ${tournamentDateTime(tournament.registrationClosesAt)}',
                      color: colors.primary,
                    ),
                  if (registration != null) ...[
                    const SizedBox(height: 8),
                    RegistrationBadge(registration: registration!),
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

class _IconLine extends StatelessWidget {
  const _IconLine({required this.icon, required this.text, this.color});

  final IconData icon;
  final String text;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final foreground = color ?? Theme.of(context).colorScheme.onSurfaceVariant;
    return Padding(
      padding: const EdgeInsets.only(top: 2),
      child: Row(
        children: [
          Icon(icon, size: 16, color: foreground),
          const SizedBox(width: 6),
          Expanded(child: Text(text, style: TextStyle(color: color), maxLines: 1, overflow: TextOverflow.ellipsis)),
        ],
      ),
    );
  }
}

class TournamentStatusChip extends StatelessWidget {
  const TournamentStatusChip({required this.tournament, super.key});

  final TournamentModel tournament;

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = switch (tournament.status) {
      'open' => (Colors.green.shade600, Colors.white),
      'in_progress' => (Colors.blue.shade600, Colors.white),
      'finished' => (Colors.grey.shade800, Colors.white),
      'cancelled' => (Colors.red.shade600, Colors.white),
      _ => (Colors.blueGrey.shade500, Colors.white),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(16)),
      child: Text(tournament.statusLabel, style: TextStyle(color: foreground, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// Registration status of the user's team.
class RegistrationBadge extends StatelessWidget {
  const RegistrationBadge({required this.registration, super.key});

  final TournamentRegistrationModel registration;

  @override
  Widget build(BuildContext context) {
    final team = registration.team?.name ?? 'Tu equipo';
    final (icon, text, color) = switch (registration.status) {
      'confirmed' => (Icons.verified_rounded, '$team · Inscripción confirmada', Colors.green.shade700),
      'pending_payment' => (Icons.hourglass_top_rounded, '$team · Falta pagar la inscripción', Colors.orange.shade800),
      'cancelled' => (Icons.cancel_outlined, '$team · Inscripción anulada', Colors.red.shade700),
      _ => (Icons.info_outline, '$team · ${registration.status}', Colors.grey.shade700),
    };
    return Row(
      children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(width: 6),
        Expanded(child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600))),
      ],
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.text, this.onRetry});

  final IconData icon;
  final String text;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 64),
        Icon(icon, size: 56, color: Theme.of(context).colorScheme.primary),
        const SizedBox(height: 12),
        Text(text, textAlign: TextAlign.center),
        if (onRetry != null) ...[
          const SizedBox(height: 12),
          Center(child: FilledButton(onPressed: onRetry, child: const Text('Reintentar'))),
        ],
      ],
    );
  }
}
