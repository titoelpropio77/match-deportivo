import 'package:flutter/material.dart';

import '../models/match_model.dart';
import '../services/match_api_service.dart';
import '../services/user_api_service.dart';
import 'create_match_screen.dart';

/// Home screen shown after an active session is validated. Lists open matches.
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    required this.apiService,
    this.onLogout,
    super.key,
  });

  final MatchApiService apiService;
  final VoidCallback? onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<MatchModel> _matches = const [];
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
      final matches = await widget.apiService.listOpenMatches();
      if (!mounted) return;

      setState(() {
        _matches = matches;
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

    setState(() => _joiningMatchIds.add(matchId));

    try {
      final updatedMatch = await widget.apiService.joinMatch(matchId);
      if (!mounted) return;

      setState(() {
        _matches = _matches
            .map((item) => item.id == updatedMatch.id ? updatedMatch : item)
            .where((item) => item.status == MatchStatus.open)
            .toList();
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

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        title: const Text('Partidos disponibles'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadMatches,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Actualizar partidos',
          ),
          if (widget.onLogout != null)
            IconButton(
              onPressed: widget.onLogout,
              icon: const Icon(Icons.logout_rounded),
              tooltip: 'Cerrar sesión',
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

  Future<void> _openCreateMatchScreen() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CreateMatchScreen(
          matchApiService: widget.apiService,
          userApiService: UserApiService(
            baseUrl: widget.apiService.baseUrl,
            token: widget.apiService.token,
          ),
        ),
      ),
    );

    if (created == true) {
      await _loadMatches();
    }
  }

  Widget _buildBody(BuildContext context) {
    if (_isLoading && _matches.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _matches.isEmpty) {
      return _ErrorState(
        message: _errorMessage(_error!),
        onRetry: _loadMatches,
      );
    }

    if (_matches.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadMatches,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 180),
            _EmptyState(),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadMatches,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        itemCount: _matches.length,
        separatorBuilder: (_, _) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final match = _matches[index];
          return _MatchCard(
            match: match,
            isJoining: match.id != null && _joiningMatchIds.contains(match.id),
            onJoin: () => _joinMatch(match),
          );
        },
      ),
    );
  }
}

class _MatchCard extends StatelessWidget {
  const _MatchCard({
    required this.match,
    required this.isJoining,
    required this.onJoin,
  });

  final MatchModel match;
  final bool isJoining;
  final VoidCallback onJoin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final date = MaterialLocalizations.of(context)
        .formatMediumDate(match.scheduledAt.toLocal());
    final time = MaterialLocalizations.of(context).formatTimeOfDay(
      TimeOfDay.fromDateTime(match.scheduledAt.toLocal()),
    );

    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    _sportLabel(match.sport),
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                _SlotsBadge(slots: match.missingPlayers),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 18,
              runSpacing: 8,
              children: [
                _InfoItem(icon: Icons.calendar_today_outlined, label: date),
                _InfoItem(icon: Icons.schedule_outlined, label: time),
                _InfoItem(
                  icon: Icons.location_on_outlined,
                  label: match.location,
                ),
              ],
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: isJoining ? null : onJoin,
                icon: isJoining
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.sports_soccer_outlined),
                label: Text(isJoining ? 'Uniendo...' : 'Unirse'),
                style: FilledButton.styleFrom(
                  backgroundColor: colors.primary,
                  padding: const EdgeInsets.symmetric(vertical: 13),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _sportLabel(MatchSport sport) {
    switch (sport) {
      case MatchSport.football5:
        return 'Fútbol 5';
      case MatchSport.football7:
        return 'Fútbol 7';
      case MatchSport.padel:
        return 'Pádel';
    }
  }
}

class _SlotsBadge extends StatelessWidget {
  const _SlotsBadge({required this.slots});

  final int slots;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: colors.secondaryContainer,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        '$slots ${slots == 1 ? 'cupo' : 'cupos'}',
        style: TextStyle(
          color: colors.onSecondaryContainer,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _InfoItem extends StatelessWidget {
  const _InfoItem({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 6),
        Text(label),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(
          Icons.sports_outlined,
          size: 56,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 12),
        Text(
          'No hay partidas abiertas',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 4),
        const Text('Vuelve a intentarlo más tarde.'),
      ],
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
