import 'package:flutter/material.dart';

import '../../models/match_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/user_api_service.dart';
import '../create_match_screen.dart';
import '../search_teams/match_detail_screen.dart';
import '../search_teams/widgets/match_card.dart';

/// Lists matches created by the user and lets them publish a new one.
class MyCourtsScreen extends StatefulWidget {
  const MyCourtsScreen({
    required this.matchApiService,
    required this.currentUserId,
    super.key,
  });

  final MatchApiService matchApiService;
  final int currentUserId;

  @override
  State<MyCourtsScreen> createState() => _MyCourtsScreenState();
}

class _MyCourtsScreenState extends State<MyCourtsScreen> {
  List<MatchModel> _activeMatches = const [];
  List<MatchModel> _pastMatches = const [];
  int _pastPage = 1;
  int _pastLastPage = 1;
  Object? _error;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadMatches();
  }

  Future<void> _loadMatches() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        widget.matchApiService.listOrganizedMatches(),
        widget.matchApiService.listPastOrganizedMatches(page: _pastPage),
      ]);
      if (!mounted) return;
      final past = results[1] as PaginatedMatches;
      setState(() {
        _activeMatches = results[0] as List<MatchModel>;
        _pastMatches = past.matches;
        _pastPage = past.currentPage;
        _pastLastPage = past.lastPage;
        _isLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _isLoading = false;
      });
    }
  }

  Future<void> _openCreateMatch() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CreateMatchScreen(
          matchApiService: widget.matchApiService,
          userApiService: UserApiService(
            baseUrl: widget.matchApiService.baseUrl,
            token: widget.matchApiService.token,
          ),
          sportApiService: SportApiService(
            baseUrl: widget.matchApiService.baseUrl,
            token: widget.matchApiService.token,
          ),
          matchLevelApiService: MatchLevelApiService(
            baseUrl: widget.matchApiService.baseUrl,
            token: widget.matchApiService.token,
          ),
          courtApiService: CourtApiService(
            baseUrl: widget.matchApiService.baseUrl,
            token: widget.matchApiService.token,
          ),
        ),
      ),
    );

    if (created == true && mounted) {
      await _loadMatches();
    }
  }

  Future<void> _openMatchDetail(MatchModel match) async {
    final matchId = match.id;
    if (matchId == null) return;

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MatchDetailScreen(
          matchId: matchId,
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );

    if (mounted) {
      await _loadMatches();
    }
  }

  String _errorMessage(Object error) {
    if (error is MatchApiException) return error.message;
    if (error is FormatException) return 'La respuesta del servidor no es válida.';
    return 'No pudimos cargar tus canchas.';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis canchas')),
      body: _buildBody(context),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openCreateMatch,
        icon: const Icon(Icons.add),
        label: const Text('Crear cancha'),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_isLoading && _activeMatches.isEmpty && _pastMatches.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _activeMatches.isEmpty && _pastMatches.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_outlined, size: 48),
              const SizedBox(height: 12),
              Text(_errorMessage(_error!), textAlign: TextAlign.center),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _loadMatches,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      );
    }

    if (_activeMatches.isEmpty && _pastMatches.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.stadium_outlined,
                size: 56,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(height: 12),
              Text(
                'Aún no has creado canchas',
                style: Theme.of(context).textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Text(
                'Publica un partido y aparecerá aquí.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _openCreateMatch,
                icon: const Icon(Icons.add),
                label: const Text('Crear cancha'),
              ),
              const SizedBox(height: 72),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadMatches,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 88),
        children: [
          _SectionTitle(title: 'Activas'),
          const SizedBox(height: 8),
          if (_activeMatches.isEmpty)
            const _SectionEmpty(message: 'No tienes canchas activas.')
          else
            for (final match in _activeMatches) ...[
              MatchCard(
                match: match,
                isJoining: false,
                onJoin: () {},
                showJoinButton: false,
                statusLabel: 'Tú lo creaste',
                onTap: () => _openMatchDetail(match),
              ),
              const SizedBox(height: 12),
            ],
          const SizedBox(height: 12),
          _SectionTitle(title: 'Pasadas'),
          const SizedBox(height: 8),
          if (_pastMatches.isEmpty)
            const _SectionEmpty(message: 'No tienes canchas pasadas.')
          else
            for (final match in _pastMatches) ...[
              MatchCard(
                match: match,
                isJoining: false,
                onJoin: () {},
                showJoinButton: false,
                statusLabel: 'Finalizada',
                compact: true,
                onTap: () => _openMatchDetail(match),
              ),
              const SizedBox(height: 8),
            ],
          if (_pastLastPage > 1)
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                IconButton(
                  tooltip: 'Página anterior',
                  onPressed: _pastPage > 1 ? () => _changePastPage(_pastPage - 1) : null,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text('$_pastPage / $_pastLastPage'),
                IconButton(
                  tooltip: 'Página siguiente',
                  onPressed: _pastPage < _pastLastPage ? () => _changePastPage(_pastPage + 1) : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
        ],
      ),
    );
  }

  Future<void> _changePastPage(int page) async {
    setState(() => _pastPage = page);
    await _loadMatches();
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
    );
  }
}

class _SectionEmpty extends StatelessWidget {
  const _SectionEmpty({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
      ),
    );
  }
}
