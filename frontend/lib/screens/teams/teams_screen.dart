import 'package:flutter/material.dart';

import '../../models/team_model.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/team_api_service.dart';
import '../../services/user_api_service.dart';
import 'team_detail_screen.dart';
import 'team_form_screen.dart';
import 'widgets/team_badge.dart';

/// "Mis equipos": teams the user plays in, and the entry to create one.
class TeamsScreen extends StatefulWidget {
  const TeamsScreen({
    required this.teamApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.currentUserId,
    super.key,
  });

  final TeamApiService teamApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final int currentUserId;

  @override
  State<TeamsScreen> createState() => _TeamsScreenState();
}

class _TeamsScreenState extends State<TeamsScreen> {
  List<TeamModel> _teams = const [];
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
      final teams = await widget.teamApiService.myTeams();
      if (!mounted) return;
      setState(() {
        _teams = teams;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is TeamApiException ? error.message : 'No pudimos cargar tus equipos.';
        _loading = false;
      });
    }
  }

  Future<void> _create() async {
    final team = await Navigator.of(context).push<TeamModel>(
      MaterialPageRoute(
        builder: (_) => TeamFormScreen(
          teamApiService: widget.teamApiService,
          userApiService: widget.userApiService,
          sportApiService: widget.sportApiService,
          matchLevelApiService: widget.matchLevelApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
    if (team == null || !mounted) return;
    await _load();
    if (mounted) _open(team);
  }

  Future<void> _open(TeamModel team) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeamDetailScreen(
          teamId: team.id,
          initialTeam: team,
          teamApiService: widget.teamApiService,
          userApiService: widget.userApiService,
          sportApiService: widget.sportApiService,
          matchLevelApiService: widget.matchLevelApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis equipos')),
      floatingActionButton: _teams.isEmpty
          ? null
          : FloatingActionButton.extended(
              onPressed: _create,
              icon: const Icon(Icons.group_add_outlined),
              label: const Text('Crear equipo'),
            ),
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
    if (_teams.isEmpty) return _EmptyTeams(onCreate: _create);

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
      itemCount: _teams.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final team = _teams[index];
        return _TeamCard(
          team: team,
          isCaptain: team.ownerId == widget.currentUserId,
          onTap: () => _open(team),
        );
      },
    );
  }
}

class _TeamCard extends StatelessWidget {
  const _TeamCard({required this.team, required this.isCaptain, required this.onTap});

  final TeamModel team;
  final bool isCaptain;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final accent = parseTeamColor(team.primaryColor);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 6, color: accent),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      TeamBadge(team: team, size: 52),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              team.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              [
                                team.sport?.name,
                                team.gender.label,
                                if (team.level != null) team.level!.name,
                              ].whereType<String>().join(' · '),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: textTheme.bodySmall,
                            ),
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Icon(Icons.groups_outlined, size: 16, color: colors.onSurfaceVariant),
                                const SizedBox(width: 4),
                                Text(
                                  '${team.membersCount} ${team.membersCount == 1 ? 'jugador' : 'jugadores'}',
                                  style: textTheme.bodySmall,
                                ),
                                if (isCaptain) ...[
                                  const SizedBox(width: 8),
                                  const CaptainTag(),
                                ],
                              ],
                            ),
                          ],
                        ),
                      ),
                      Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class CaptainTag extends StatelessWidget {
  const CaptainTag({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: Colors.amber.shade100, borderRadius: BorderRadius.circular(12)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.star_rounded, size: 12, color: Colors.amber.shade900),
          const SizedBox(width: 2),
          Text(
            'Capitán',
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.amber.shade900),
          ),
        ],
      ),
    );
  }
}

class _EmptyTeams extends StatelessWidget {
  const _EmptyTeams({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    return ListView(
      padding: const EdgeInsets.all(32),
      children: [
        const SizedBox(height: 48),
        CircleAvatar(
          radius: 48,
          backgroundColor: colors.primaryContainer,
          child: Icon(Icons.groups_rounded, size: 48, color: colors.onPrimaryContainer),
        ),
        const SizedBox(height: 24),
        Text('Todavía no tienes equipos', textAlign: TextAlign.center, style: textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          'Arma tu equipo con tus compañeros de siempre y agrégalo completo a un partido con un solo toque.',
          textAlign: TextAlign.center,
          style: textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
        ),
        const SizedBox(height: 24),
        Center(
          child: FilledButton.icon(
            onPressed: onCreate,
            icon: const Icon(Icons.group_add_outlined),
            label: const Text('Crear mi primer equipo'),
          ),
        ),
      ],
    );
  }
}
