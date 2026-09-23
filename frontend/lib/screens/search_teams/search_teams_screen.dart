import 'package:flutter/material.dart';

import '../../models/match_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/user_api_service.dart';
import '../create_match_screen.dart';
import 'match_detail_screen.dart';
import 'widgets/match_card.dart';

/// "Buscar equipos" screen: shows the user's joined matches and open matches to join.
class SearchTeamsScreen extends StatefulWidget {
  const SearchTeamsScreen({
    required this.matchApiService,
    required this.currentUserId,
    super.key,
  });

  final MatchApiService matchApiService;
  final int currentUserId;

  @override
  State<SearchTeamsScreen> createState() => _SearchTeamsScreenState();
}

class _SearchTeamsScreenState extends State<SearchTeamsScreen> {
  List<MatchModel> _myMatches = const [];
  List<MatchModel> _openMatches = const [];
  Object? _error;
  bool _isLoading = true;
  final Set<int> _joiningMatchIds = {};

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
        widget.matchApiService.listMyMatches(),
        widget.matchApiService.listOpenMatches(),
      ]);
      if (!mounted) return;

      final myMatches = results[0];
      final myMatchIds = myMatches.map((match) => match.id).toSet();

      setState(() {
        _myMatches = myMatches;
        _openMatches = results[1]
            .where((match) => !myMatchIds.contains(match.id))
            .toList();
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

  Future<void> _joinMatch(MatchModel match) async {
    final matchId = match.id;
    if (matchId == null || _joiningMatchIds.contains(matchId)) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Unirse al partido'),
        content: const Text('¿Confirmas que quieres unirte a este partido?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Confirmar'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _joiningMatchIds.add(matchId));

    try {
      final updatedMatch = await widget.matchApiService.joinMatch(matchId);
      if (!mounted) return;

      setState(() {
        _openMatches = _openMatches.where((item) => item.id != matchId).toList();
        _myMatches = [updatedMatch, ..._myMatches];
        _joiningMatchIds.remove(matchId);
      });
      _showMessage('Te uniste a la partida');
    } catch (error) {
      if (!mounted) return;

      setState(() => _joiningMatchIds.remove(matchId));
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError
              ? Theme.of(context).colorScheme.error
              : Theme.of(context).colorScheme.inverseSurface,
        ),
      );
  }

  String _errorMessage(Object error) {
    if (error is MatchApiException) return error.message;
    if (error is FormatException) return 'La respuesta del servidor no es válida.';
    return 'No pudimos conectar con el servidor.';
  }

  Future<void> _openCreateMatchScreen() async {
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

    if (created == true) {
      await _loadMatches();
    }
  }

  void _openMatchDetail(MatchModel match) {
    final matchId = match.id;
    if (matchId == null) return;

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MatchDetailScreen(
          matchId: matchId,
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buscar equipos'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadMatches,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Actualizar partidos',
          ),
        ],
      ),
      body: _buildBody(context),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openCreateMatchScreen,
        icon: const Icon(Icons.add),
        label: const Text('Crear partido'),
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    final hasAnyData = _myMatches.isNotEmpty || _openMatches.isNotEmpty;

    if (_isLoading && !hasAnyData) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && !hasAnyData) {
      return _ErrorState(
        message: _errorMessage(_error!),
        onRetry: _loadMatches,
      );
    }

    return RefreshIndicator(
      onRefresh: _loadMatches,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          _SectionHeader('Equipos en los que estoy apuntado'),
          const SizedBox(height: 12),
          if (_myMatches.isEmpty)
            const _InlineEmptyState(message: 'No tienes partidos próximos.')
          else
            ..._myMatches.map(
              (match) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: MatchCard(
                  match: match,
                  isJoining: false,
                  onJoin: () {},
                  showJoinButton: false,
                  onTap: () => _openMatchDetail(match),
                ),
              ),
            ),
          const SizedBox(height: 28),
          _SectionHeader('Partidos abiertos'),
          const SizedBox(height: 12),
          if (_openMatches.isEmpty)
            const _InlineEmptyState(message: 'No hay partidas abiertas.')
          else
            ..._openMatches.map(
              (match) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: MatchCard(
                  match: match,
                  isJoining: match.id != null && _joiningMatchIds.contains(match.id),
                  onJoin: () => _joinMatch(match),
                  onTap: () => _openMatchDetail(match),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
          ),
    );
  }
}

class _InlineEmptyState extends StatelessWidget {
  const _InlineEmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Reintentar'),
            ),
          ],
        ),
      ),
    );
  }
}
