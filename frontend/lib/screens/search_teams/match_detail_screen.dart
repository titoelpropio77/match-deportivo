import 'package:flutter/material.dart';

import '../../models/match_model.dart';
import '../../services/match_api_service.dart';
import 'widgets/court_info_header.dart';
import 'widgets/match_players_list.dart';

/// Full detail of a match: court info, schedule, players and join/leave actions.
class MatchDetailScreen extends StatefulWidget {
  const MatchDetailScreen({
    required this.matchId,
    required this.matchApiService,
    required this.currentUserId,
    super.key,
  });

  final int matchId;
  final MatchApiService matchApiService;
  final int currentUserId;

  @override
  State<MatchDetailScreen> createState() => _MatchDetailScreenState();
}

class _MatchDetailScreenState extends State<MatchDetailScreen> {
  MatchModel? _match;
  Object? _error;
  bool _isLoading = true;
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    _loadMatch();
  }

  Future<void> _loadMatch() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final match = await widget.matchApiService.getMatch(widget.matchId);
      if (!mounted) return;
      setState(() {
        _match = match;
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

  bool get _isOrganizer => _match?.organizerId == widget.currentUserId;

  bool get _isJoined =>
      _match?.players?.any((player) => player.userId == widget.currentUserId) ?? false;

  Future<bool> _confirm({required String title, required String message}) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
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
    return confirmed ?? false;
  }

  Future<void> _handleJoin() async {
    final confirmed = await _confirm(
      title: 'Unirse al partido',
      message: '¿Confirmas que quieres unirte a este partido?',
    );
    if (!confirmed) return;

    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.joinMatch(widget.matchId);
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage('Te uniste a la partida');
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleLeave() async {
    final confirmed = await _confirm(
      title: 'Salir del partido',
      message: '¿Confirmas que quieres salir de este partido?',
    );
    if (!confirmed) return;

    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.leaveMatch(widget.matchId);
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage('Saliste de la partida');
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
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
    return 'No pudimos completar la acción.';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detalle del partido')),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null || _match == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_outlined, size: 48),
              const SizedBox(height: 12),
              Text(
                _error != null ? _errorMessage(_error!) : 'Partido no encontrado.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _loadMatch,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      );
    }

    final match = _match!;
    final date = MaterialLocalizations.of(context).formatMediumDate(match.startTime.toLocal());
    final startTime = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.startTime.toLocal()));
    final endTime = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.endTime.toLocal()));

    return ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        if (match.court != null) CourtInfoHeader(court: match.court!),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  Chip(label: Text(match.sport?.name ?? 'Deporte')),
                  Chip(label: Text(match.level?.name ?? 'Nivel')),
                  Chip(label: Text('${match.missingPlayers} cupos libres')),
                ],
              ),
              const SizedBox(height: 16),
              _InfoRow(icon: Icons.calendar_today_outlined, label: date),
              const SizedBox(height: 8),
              _InfoRow(icon: Icons.schedule_outlined, label: '$startTime - $endTime'),
              const SizedBox(height: 24),
              MatchPlayersList(
                organizerName: match.organizer?.nickname ??
                    match.organizer?.name ??
                    'Organizador',
                players: match.players ?? const [],
              ),
              const SizedBox(height: 28),
              if (!_isOrganizer)
                SizedBox(
                  width: double.infinity,
                  child: _isJoined
                      ? OutlinedButton.icon(
                          onPressed: _isProcessing ? null : _handleLeave,
                          icon: _isProcessing
                              ? const SizedBox.square(
                                  dimension: 17,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.exit_to_app_rounded),
                          label: Text(_isProcessing ? 'Procesando...' : 'Salir del partido'),
                        )
                      : FilledButton.icon(
                          onPressed: _isProcessing ? null : _handleJoin,
                          icon: _isProcessing
                              ? const SizedBox.square(
                                  dimension: 17,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.sports_soccer_outlined),
                          label: Text(_isProcessing ? 'Procesando...' : 'Unirse'),
                        ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 8),
        Text(label),
      ],
    );
  }
}
