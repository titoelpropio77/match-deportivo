import 'package:flutter/material.dart';

import '../../models/team_model.dart';
import '../../models/tournament_model.dart';
import '../../services/team_api_service.dart';
import '../../services/tournament_api_service.dart';
import '../teams/widgets/team_badge.dart';
import 'tournament_format.dart';
import 'tournament_payment_screen.dart';
import 'tournaments_screen.dart';

/// Tournament detail: info, registered teams, fixture and standings; the captain signs up a team here.
class TournamentDetailScreen extends StatefulWidget {
  const TournamentDetailScreen({
    required this.tournamentId,
    required this.tournamentApiService,
    required this.teamApiService,
    required this.currentUserId,
    super.key,
  });

  final int tournamentId;
  final TournamentApiService tournamentApiService;
  final TeamApiService teamApiService;
  final int currentUserId;

  @override
  State<TournamentDetailScreen> createState() => _TournamentDetailScreenState();
}

class _TournamentDetailScreenState extends State<TournamentDetailScreen> {
  TournamentModel? _tournament;
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
      final tournament = await widget.tournamentApiService.show(widget.tournamentId);
      if (!mounted) return;
      setState(() {
        _tournament = tournament;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is TournamentApiException ? error.message : 'No pudimos cargar el torneo.';
        _loading = false;
      });
    }
  }

  Future<void> _registerTeam() async {
    final tournament = _tournament!;
    final team = await showModalBottomSheet<TeamModel>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _TeamPickerSheet(
        tournament: tournament,
        teamApiService: widget.teamApiService,
        currentUserId: widget.currentUserId,
      ),
    );
    if (team == null || !mounted) return;

    try {
      final registration = await widget.tournamentApiService.register(tournament.id, team.id);
      if (!mounted) return;
      if (registration.isConfirmed) {
        _snack('¡${team.name} quedó inscrito en ${tournament.name}!');
      } else {
        await _pay(registration);
      }
      _load();
    } catch (error) {
      _snack(error is TournamentApiException ? error.message : 'No pudimos inscribir al equipo.');
    }
  }

  Future<void> _pay(TournamentRegistrationModel registration) async {
    final paid = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => TournamentPaymentScreen(
          tournament: _tournament!,
          registration: registration,
          tournamentApiService: widget.tournamentApiService,
        ),
      ),
    );
    if (paid == true && mounted) _load();
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final tournament = _tournament;
    if (tournament == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Torneo')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error ?? 'No encontramos el torneo.'),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: _load, child: const Text('Reintentar')),
                  ],
                ),
              ),
      );
    }

    final pending = tournament.myRegistrations.where((item) => item.isPendingPayment).firstOrNull;
    final registered = tournament.myRegistrations.where((item) => item.isConfirmed).firstOrNull;

    return DefaultTabController(
      length: 4,
      child: Scaffold(
        bottomNavigationBar: tournament.acceptsRegistrations && pending == null && registered == null
            ? SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton.icon(
                    onPressed: _registerTeam,
                    icon: const Icon(Icons.how_to_reg_outlined),
                    label: Text('Inscribir mi equipo · ${tournamentFee(tournament)}'),
                  ),
                ),
              )
            : null,
        body: NestedScrollView(
          headerSliverBuilder: (context, _) => [
            SliverAppBar(
              pinned: true,
              expandedHeight: 180,
              title: Text(tournament.name),
              flexibleSpace: FlexibleSpaceBar(background: _Cover(url: tournament.coverUrl)),
            ),
            SliverToBoxAdapter(
              child: _Header(
                tournament: tournament,
                pending: pending,
                registered: registered,
                onPay: pending == null ? null : () => _pay(pending),
              ),
            ),
            const SliverPersistentHeader(pinned: true, delegate: _TabsHeader()),
          ],
          body: TabBarView(
            children: [
              _InfoTab(tournament: tournament),
              _TeamsTab(tournament: tournament),
              _FixtureTab(tournament: tournament),
              _StandingsTab(tournament: tournament),
            ],
          ),
        ),
      ),
    );
  }
}

class _Cover extends StatelessWidget {
  const _Cover({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final placeholder = Container(
      decoration: BoxDecoration(gradient: LinearGradient(colors: [colors.primary, colors.tertiary])),
      child: Center(child: Icon(Icons.emoji_events_rounded, size: 72, color: colors.onPrimary.withValues(alpha: 0.8))),
    );
    return url == null ? placeholder : Image.network(url!, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder);
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.tournament, required this.pending, required this.registered, required this.onPay});

  final TournamentModel tournament;
  final TournamentRegistrationModel? pending;
  final TournamentRegistrationModel? registered;
  final VoidCallback? onPay;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              TournamentStatusChip(tournament: tournament),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  tournament.isFree ? 'Gratis' : '${tournamentFee(tournament)} por equipo',
                  textAlign: TextAlign.end,
                  maxLines: 2,
                  style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800, color: colors.primary),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            [tournament.sport?.name, tournament.formatLabel, tournament.gender.label].whereType<String>().join(' · '),
            style: textTheme.bodyMedium,
          ),
          Text('${tournament.venueName ?? ''}${tournament.cityName == null ? '' : ' · ${tournament.cityName}'}', style: textTheme.bodySmall),
          if (registered != null) ...[
            const SizedBox(height: 12),
            _Banner(
              color: Colors.green.shade50,
              border: Colors.green.shade300,
              child: RegistrationBadge(registration: registered!),
            ),
          ],
          if (pending != null) ...[
            const SizedBox(height: 12),
            _Banner(
              color: Colors.orange.shade50,
              border: Colors.orange.shade300,
              child: Row(
                children: [
                  Expanded(child: RegistrationBadge(registration: pending!)),
                  if (onPay != null) FilledButton(onPressed: onPay, child: const Text('Pagar')),
                ],
              ),
            ),
          ],
          if (tournament.acceptsRegistrations) ...[
            const SizedBox(height: 8),
            Text(
              'Quedan ${tournament.spotsLeft} cupos · inscripciones hasta el ${tournamentDateTime(tournament.registrationClosesAt)}',
              style: textTheme.bodySmall?.copyWith(color: colors.primary, fontWeight: FontWeight.w600),
            ),
          ],
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.color, required this.border, required this.child});

  final Color color;
  final Color border;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: color, border: Border.all(color: border), borderRadius: BorderRadius.circular(10)),
      child: child,
    );
  }
}

class _TabsHeader extends SliverPersistentHeaderDelegate {
  const _TabsHeader();

  static const _height = 48.0;

  @override
  double get minExtent => _height;

  @override
  double get maxExtent => _height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      child: const TabBar(
        isScrollable: true,
        tabAlignment: TabAlignment.start,
        tabs: [Tab(text: 'Información'), Tab(text: 'Equipos'), Tab(text: 'Fixture'), Tab(text: 'Tabla')],
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _TabsHeader oldDelegate) => false;
}

class _InfoTab extends StatelessWidget {
  const _InfoTab({required this.tournament});

  final TournamentModel tournament;

  @override
  Widget build(BuildContext context) {
    final rows = [
      (Icons.calendar_month_outlined, 'Fechas',
          '${tournamentDate(tournament.startsOn)}${tournament.endsOn == null ? '' : ' al ${tournamentDate(tournament.endsOn!)}'}'),
      (Icons.timer_outlined, 'Cierre de inscripciones', tournamentDateTime(tournament.registrationClosesAt)),
      (Icons.payments_outlined, 'Inscripción', tournament.isFree ? 'Gratis' : '${tournamentFee(tournament)} por equipo (pago con QR)'),
      (Icons.groups_outlined, 'Cupo', '${tournament.teamsCount} de ${tournament.maxTeams} equipos'),
      (Icons.person_outline, 'Jugadores por equipo', tournamentPlayers(tournament)),
      (Icons.wc_outlined, 'Categoría', tournament.gender.label),
      if (tournament.level != null) (Icons.trending_up_rounded, 'Nivel', tournament.level!.name),
      (Icons.place_outlined, 'Centro deportivo', [tournament.venueName, tournament.venueAddress].whereType<String>().join(' · ')),
    ];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final (icon, label, value) in rows)
          ListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            leading: Icon(icon),
            title: Text(label),
            subtitle: Text(value),
          ),
        if ((tournament.prizes ?? '').isNotEmpty) _TextBlock(icon: Icons.emoji_events_outlined, title: 'Premios', text: tournament.prizes!),
        if ((tournament.description ?? '').isNotEmpty) _TextBlock(icon: Icons.info_outline, title: 'Descripción', text: tournament.description!),
        if ((tournament.rules ?? '').isNotEmpty) _TextBlock(icon: Icons.gavel_outlined, title: 'Reglamento', text: tournament.rules!),
        const SizedBox(height: 80),
      ],
    );
  }
}

class _TextBlock extends StatelessWidget {
  const _TextBlock({required this.icon, required this.title, required this.text});

  final IconData icon;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(top: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [Icon(icon, size: 20), const SizedBox(width: 8), Text(title, style: Theme.of(context).textTheme.titleSmall)]),
            const SizedBox(height: 6),
            Text(text),
          ],
        ),
      ),
    );
  }
}

TeamBadgeView _badge(TournamentTeam team, {double size = 36}) =>
    TeamBadgeView(initials: team.initials, color: team.primaryColor, logoUrl: team.logoUrl, size: size);

class _TeamsTab extends StatelessWidget {
  const _TeamsTab({required this.tournament});

  final TournamentModel tournament;

  @override
  Widget build(BuildContext context) {
    if (tournament.teams.isEmpty) {
      return const _Empty(text: 'Todavía no hay equipos confirmados.');
    }
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final team in tournament.teams)
          ListTile(
            leading: _badge(team, size: 40),
            title: Text(team.name),
            subtitle: team.membersCount == null ? null : Text('${team.membersCount} jugadores'),
          ),
      ],
    );
  }
}

class _FixtureTab extends StatelessWidget {
  const _FixtureTab({required this.tournament});

  final TournamentModel tournament;

  @override
  Widget build(BuildContext context) {
    if (tournament.games.isEmpty) {
      return const _Empty(text: 'El fixture se publicará cuando cierren las inscripciones.');
    }
    final rounds = <String, List<TournamentGameModel>>{};
    for (final game in tournament.games) {
      rounds.putIfAbsent(game.round, () => []).add(game);
    }
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final entry in rounds.entries) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 8, 4, 4),
            child: Text(entry.key, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          ),
          for (final game in entry.value) _GameCard(game: game),
        ],
      ],
    );
  }
}

class _GameCard extends StatelessWidget {
  const _GameCard({required this.game});

  final TournamentGameModel game;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    Widget side(TournamentTeam? team, {required bool home}) => Expanded(
          child: Row(
            mainAxisAlignment: home ? MainAxisAlignment.end : MainAxisAlignment.start,
            children: [
              if (!home && team != null) ...[_badge(team, size: 28), const SizedBox(width: 6)],
              Flexible(
                child: Text(
                  team?.name ?? 'Por definir',
                  textAlign: home ? TextAlign.end : TextAlign.start,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              if (home && team != null) ...[const SizedBox(width: 6), _badge(team, size: 28)],
            ],
          ),
        );

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Row(
              children: [
                side(game.homeTeam, home: true),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: game.isPlayed
                      ? Text('${game.homeScore} – ${game.awayScore}', style: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))
                      : Text(game.status == 'cancelled' ? 'Susp.' : 'vs', style: textTheme.titleMedium),
                ),
                side(game.awayTeam, home: false),
              ],
            ),
            if (game.scheduledAt != null || game.field != null) ...[
              const SizedBox(height: 6),
              Text(
                [if (game.scheduledAt != null) tournamentDateTime(game.scheduledAt!), game.field].whereType<String>().join(' · '),
                style: textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StandingsTab extends StatelessWidget {
  const _StandingsTab({required this.tournament});

  final TournamentModel tournament;

  @override
  Widget build(BuildContext context) {
    if (tournament.standings.isEmpty) {
      return const _Empty(text: 'La tabla aparece cuando hay equipos confirmados.');
    }
    final header = Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700);
    Widget cell(String text, {TextStyle? style, double width = 30}) =>
        SizedBox(width: width, child: Text(text, textAlign: TextAlign.center, style: style));

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            cell('#', style: header, width: 24),
            Expanded(child: Text('Equipo', style: header)),
            cell('PJ', style: header),
            cell('G', style: header),
            cell('E', style: header),
            cell('P', style: header),
            cell('DIF', style: header, width: 36),
            cell('PTS', style: header, width: 36),
          ],
        ),
        const Divider(),
        for (final (index, row) in tournament.standings.indexed)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              children: [
                cell('${index + 1}', width: 24),
                Expanded(
                  child: Row(
                    children: [
                      _badge(row.team, size: 24),
                      const SizedBox(width: 6),
                      Flexible(child: Text(row.team.name, overflow: TextOverflow.ellipsis)),
                    ],
                  ),
                ),
                cell('${row.played}'),
                cell('${row.won}'),
                cell('${row.drawn}'),
                cell('${row.lost}'),
                cell('${row.goalDifference > 0 ? '+' : ''}${row.goalDifference}', width: 36),
                cell('${row.points}', width: 36, style: const TextStyle(fontWeight: FontWeight.w800)),
              ],
            ),
          ),
        const SizedBox(height: 8),
        Text('3 puntos por victoria, 1 por empate.', style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(32),
      children: [const SizedBox(height: 32), Text(text, textAlign: TextAlign.center)],
    );
  }
}

/// The user's teams of the tournament sport; only teams they captain and that meet the
/// player count can be picked. Pops the chosen [TeamModel].
class _TeamPickerSheet extends StatefulWidget {
  const _TeamPickerSheet({required this.tournament, required this.teamApiService, required this.currentUserId});

  final TournamentModel tournament;
  final TeamApiService teamApiService;
  final int currentUserId;

  @override
  State<_TeamPickerSheet> createState() => _TeamPickerSheetState();
}

class _TeamPickerSheetState extends State<_TeamPickerSheet> {
  late final Future<List<TeamModel>> _teams = widget.teamApiService.myTeams(sportId: widget.tournament.sport?.id);

  /// Why a team cannot be registered, or null if it can.
  String? _blocker(TeamModel team) {
    final tournament = widget.tournament;
    if (team.ownerId != widget.currentUserId) return 'Solo el capitán puede inscribirlo';
    if (tournament.gender.value != 'mixed' && team.gender != tournament.gender) {
      return 'El torneo es de categoría ${tournament.gender.label.toLowerCase()}';
    }
    if (team.membersCount < tournament.minPlayersPerTeam) {
      return 'Necesita al menos ${tournament.minPlayersPerTeam} jugadores (tiene ${team.membersCount})';
    }
    final max = tournament.maxPlayersPerTeam;
    if (max != null && team.membersCount > max) return 'Máximo $max jugadores (tiene ${team.membersCount})';
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final tournament = widget.tournament;
    return SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.6,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Inscribir equipo', style: Theme.of(context).textTheme.titleLarge),
            Text(
              '${tournament.sport?.name ?? ''} · ${tournamentPlayers(tournament)}'
              '${tournament.isFree ? '' : ' · ${tournamentFee(tournament)}'}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 12),
            Expanded(
              child: FutureBuilder<List<TeamModel>>(
                future: _teams,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  final teams = snapshot.data ?? const [];
                  if (snapshot.hasError || teams.isEmpty) {
                    return Center(
                      child: Text(
                        snapshot.hasError
                            ? 'No pudimos cargar tus equipos.'
                            : 'No tienes equipos de ${tournament.sport?.name ?? 'este deporte'}.\nCréalo en "Equipos" desde el inicio y vuelve.',
                        textAlign: TextAlign.center,
                      ),
                    );
                  }
                  return ListView(
                    children: [
                      for (final team in teams)
                        Builder(builder: (context) {
                          final blocker = _blocker(team);
                          return ListTile(
                            enabled: blocker == null,
                            leading: TeamBadge(team: team, size: 40),
                            title: Text(team.name),
                            subtitle: Text(blocker ?? '${team.membersCount} jugadores'),
                            trailing: blocker == null ? const Icon(Icons.chevron_right) : const Icon(Icons.block, size: 18),
                            onTap: blocker == null ? () => Navigator.of(context).pop(team) : null,
                          );
                        }),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
