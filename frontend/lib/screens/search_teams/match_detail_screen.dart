import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../../models/match_model.dart';
import '../../models/match_player_model.dart';
import '../../models/rating_tag_model.dart';
import '../../models/user_model.dart';
import '../../services/match_api_service.dart';
import '../../services/user_api_service.dart';
import '../profile/player_profile_screen.dart';
import '../teams/widgets/team_badge.dart';
import 'widgets/add_player_sheet.dart';
import 'widgets/court_info_header.dart';
import 'widgets/finish_match_dialog.dart';
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
  late final _userApiService = UserApiService(
    baseUrl: widget.matchApiService.baseUrl,
    token: widget.matchApiService.token,
  );

  @override
  void dispose() {
    _userApiService.dispose();
    super.dispose();
  }

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

  MatchPlayerModel? get _currentPlayer {
    final players = _match?.players;
    if (players == null) return null;
    for (final player in players) {
      if (player.userId == widget.currentUserId) return player;
    }
    return null;
  }

  bool get _isJoined => _currentPlayer != null && !_isReserved;

  bool get _isPending => _currentPlayer?.isPending ?? false;

  bool get _isReserved => _currentPlayer?.isReserved ?? false;

  bool get _isOrganizer => _match?.organizerId == widget.currentUserId;

  bool get _hasNotStarted =>
      _match != null && _match!.startTime.toLocal().isAfter(DateTime.now());

  bool get _hasConcluded =>
      _match != null &&
      (_match!.status == MatchStatus.finished ||
          !_match!.endTime.toLocal().isAfter(DateTime.now()));

  bool get _isFinished => _match?.status == MatchStatus.finished;

  bool get _canDelete => _isOrganizer && _hasNotStarted && !_hasConcluded;

  bool get _canFinish =>
      _isOrganizer &&
      _hasConcluded &&
      !_isFinished &&
      _match?.status != MatchStatus.cancelled;

  Future<bool> _confirm({
    required String title,
    required String message,
  }) async {
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
    final joiningReserve = !_hasFreeSlots;
    final confirmed = await _confirm(
      title: joiningReserve ? 'Unirse a reserva' : 'Unirse al partido',
      message: joiningReserve
          ? 'El partido está lleno. ¿Quieres entrar a la lista de reserva?'
          : '¿Confirmas que quieres unirte a este partido?',
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
      final myPlayer = updated.players?.where(
        (player) => player.userId == widget.currentUserId,
      );
      final mine = myPlayer == null || myPlayer.isEmpty ? null : myPlayer.first;
      _showMessage(
        mine?.isReserved == true
            ? 'Quedaste en la lista de reserva.'
            : mine?.isPending == true
            ? 'Solicitud enviada. El organizador debe confirmarte.'
            : 'Te uniste a la partida',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleDelete() async {
    final confirmed = await _confirm(
      title: 'Eliminar cancha',
      message: '¿Confirmas que quieres eliminar este partido? Dejará de aparecer para los jugadores.',
    );
    if (!confirmed) return;

    setState(() => _isProcessing = true);
    try {
      await widget.matchApiService.deleteMatch(widget.matchId);
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleReviewPlayer(
    MatchPlayerModel player,
    String action,
  ) async {
    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.reviewPlayer(
        matchId: widget.matchId,
        playerId: player.userId,
        action: action,
      );
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage(switch (action) {
        'accept_always' => 'Aceptaste a este jugador para siempre.',
        'reject' => 'Rechazaste la solicitud.',
        _ => 'Aceptaste al jugador en este partido.',
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  void _openPlayerProfile(int userId) {
    final players = _match?.players ?? const <MatchPlayerModel>[];
    final user = players.where((player) => player.user?.id == userId).firstOrNull?.user ??
        (_match?.organizer?.id == userId ? _match?.organizer : null);
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PlayerProfileScreen(
          userId: userId,
          initialUser: user,
          userApiService: _userApiService,
        ),
      ),
    );
  }

  Future<void> _showPlayerReview(MatchPlayerModel player) async {
    final name = player.user?.nickname ?? player.user?.name ?? 'Jugador';
    final action = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(name),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (player.user?.name != null) Text(player.user!.name),
            if (player.user?.email != null) ...[
              const SizedBox(height: 4),
              Text(player.user!.email),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: () => Navigator.of(context).pop('accept_once'),
              child: const Text('Aceptar por esta vez'),
            ),
            const SizedBox(height: 8),
            FilledButton.tonal(
              onPressed: () => Navigator.of(context).pop('accept_always'),
              child: const Text('Aceptar por siempre a este jugador'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => Navigator.of(context).pop('reject'),
              child: const Text('Rechazar'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cerrar'),
          ),
        ],
      ),
    );
    if (action == null || !mounted) return;
    await _handleReviewPlayer(player, action);
  }

  Future<void> _handleRemovePlayer(MatchPlayerModel player) async {
    final name = player.user?.nickname ?? player.user?.name ?? 'este jugador';
    final confirmed = await _confirm(
      title: 'Quitar jugador',
      message: player.isReserved
          ? '¿Confirmas que quieres quitar a $name de la reserva?'
          : '¿Confirmas que quieres quitar a $name de este partido?',
    );
    if (!confirmed) return;

    final reservedBefore =
        _match?.players?.where((item) => item.isReserved).length ?? 0;

    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.removePlayer(
        matchId: widget.matchId,
        playerId: player.userId,
      );
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      final reservedAfter =
          updated.players?.where((item) => item.isReserved).length ?? 0;
      final promoted = !player.isReserved && reservedAfter < reservedBefore;
      _showMessage(
        promoted
            ? 'Quitaste al jugador. El siguiente de reserva pasó al partido.'
            : player.isReserved
            ? 'Quitaste al jugador de la reserva'
            : 'Quitaste al jugador del partido',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _openAddPlayer({required bool toReserve}) async {
    if (_match == null) return;
    if (!toReserve && !_hasFreeSlots) {
      _showMessage('No hay cupos disponibles. Usa la reserva.', isError: true);
      return;
    }

    final excludedIds = {
      widget.currentUserId,
      ...?_match?.players?.map((player) => player.userId),
    };

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => AddPlayerSheet(
        title: toReserve ? 'Agregar a reserva' : 'Agregar jugador',
        userApiService: _userApiService,
        excludedUserIds: excludedIds,
        onSelect: (user) {
          Navigator.of(context).pop();
          _handleAddPlayer(user, toReserve: toReserve);
        },
      ),
    );
  }

  Future<void> _handleAddPlayer(
    UserModel user, {
    required bool toReserve,
  }) async {
    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.addPlayer(
        matchId: widget.matchId,
        userId: user.id,
      );
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage(
        toReserve
            ? 'Agregaste a ${user.nickname ?? user.name} a la reserva'
            : 'Agregaste a ${user.nickname ?? user.name}',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleLeave() async {
    final confirmed = await _confirm(
      title: _isReserved
          ? 'Salir de reserva'
          : _isPending
          ? 'Cancelar solicitud'
          : 'Salir del partido',
      message: _isReserved
          ? '¿Confirmas que quieres salir de la lista de reserva?'
          : _isPending
          ? '¿Confirmas que quieres cancelar tu solicitud?'
          : '¿Confirmas que quieres salir de este partido?',
    );
    if (!confirmed) return;

    final leavingReserve = _isReserved;
    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.leaveMatch(widget.matchId);
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage(
        leavingReserve ? 'Saliste de la reserva' : 'Saliste de la partida',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleFinish() async {
    final confirmed = await _confirm(
      title: 'Terminar partido',
      message: '¿Confirmas que este partido ya concluyó? Después no se podrá salir ni eliminar.',
    );
    if (!confirmed || !mounted) return;

    setState(() => _isProcessing = true);
    late final List<RatingTagModel> tags;
    try {
      tags = await widget.matchApiService.listRatingTags(widget.matchId);
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
      return;
    }

    if (!mounted) return;
    setState(() => _isProcessing = false);

    final players = (_match?.players ?? const <MatchPlayerModel>[])
        .where(
          (player) =>
              !player.isPending &&
              !player.isReserved &&
              player.userId != widget.currentUserId,
        )
        .toList();

    final ratings = await showDialog<List<PlayerRatingInput>>(
      context: context,
      builder: (context) => FinishMatchDialog(players: players, tags: tags),
    );
    if (ratings == null || !mounted) return;

    setState(() => _isProcessing = true);
    try {
      final updated = await widget.matchApiService.finishMatch(
        widget.matchId,
        ratings: ratings.map((rating) => rating.toJson()).toList(),
      );
      if (!mounted) return;
      setState(() {
        _match = updated;
        _isProcessing = false;
      });
      _showMessage(
        ratings.isEmpty
            ? 'Terminaste el partido.'
            : 'Terminaste el partido y guardaste las calificaciones.',
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _isProcessing = false);
      _showMessage(_errorMessage(error), isError: true);
    }
  }

  Future<void> _handleShare() async {
    final match = _match;
    if (match == null) return;

    final court = match.court?.name ?? 'una cancha';
    final sport = match.sport?.name ?? 'un partido';
    final when = MaterialLocalizations.of(context)
        .formatMediumDate(match.startTime.toLocal());
    final start = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.startTime.toLocal()));

    try {
      await SharePlus.instance.share(
        ShareParams(
          text:
              'Te invito a jugar $sport en $court el $when a las $start. Ábrelo en la app para ver la cancha.',
        ),
      );
    } catch (_) {
      if (!mounted) return;
      _showMessage(
        'No pudimos abrir las opciones para compartir.',
        isError: true,
      );
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

  bool get _isOpen => _match?.status == MatchStatus.open;

  bool get _hasFreeSlots => (_match?.missingPlayers ?? 0) > 0;

  bool get _isFull => _match?.status == MatchStatus.full || !_hasFreeSlots;

  bool get _canJoin =>
      !_hasConcluded && !_isJoined && !_isReserved && _isOpen && _hasFreeSlots;

  bool get _canJoinReserve =>
      !_hasConcluded &&
      !_isJoined &&
      !_isReserved &&
      _isFull &&
      _match?.status != MatchStatus.cancelled;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detalle del partido')),
      body: _buildBody(context),
      bottomNavigationBar: _match == null || _isLoading || _error != null
          ? null
          : _MatchActionBar(
              isJoined: _isJoined,
              isPending: _isPending,
              isReserved: _isReserved,
              isProcessing: _isProcessing,
              canJoin: _canJoin,
              canJoinReserve: _canJoinReserve,
              canDelete: _canDelete,
              canFinish: _canFinish,
              hasConcluded: _hasConcluded,
              isFinished: _isFinished,
              isCancelled: _match?.status == MatchStatus.cancelled,
              onShare: _handleShare,
              onJoin: _handleJoin,
              onLeave: _handleLeave,
              onDelete: _handleDelete,
              onFinish: _handleFinish,
            ),
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
                _error != null
                    ? _errorMessage(_error!)
                    : 'Partido no encontrado.',
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
    final date = MaterialLocalizations.of(context)
        .formatMediumDate(match.startTime.toLocal());
    final startTime = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.startTime.toLocal()));
    final endTime = MaterialLocalizations.of(context)
        .formatTimeOfDay(TimeOfDay.fromDateTime(match.endTime.toLocal()));

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 48),
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
                  Chip(label: Text(match.gender.label)),
                  Chip(label: Text('${match.missingPlayers} cupos libres')),
                ],
              ),
              const SizedBox(height: 16),
              _InfoRow(icon: Icons.calendar_today_outlined, label: date),
              const SizedBox(height: 8),
              _InfoRow(
                icon: Icons.schedule_outlined,
                label: '$startTime - $endTime',
              ),
              if (match.courtFields.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text('Canchas', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: match.courtFields
                      .map(
                        (field) => Chip(
                          avatar: const Icon(
                            Icons.sports_tennis_outlined,
                            size: 18,
                          ),
                          label: Text(field.label),
                        ),
                      )
                      .toList(),
                ),
              ],
              if (match.teams.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text('Equipos', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final team in match.teams)
                      Chip(
                        avatar: TeamBadge(team: team, size: 24),
                        label: Text(team.name),
                      ),
                  ],
                ),
              ],
              if (match.paymentQrUrl != null &&
                  match.paymentQrUrl!.isNotEmpty) ...[
                const SizedBox(height: 24),
                Text(
                  'Pago de la cancha',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Text(
                  'Escanea este QR para pagar la cancha al organizador.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 12),
                Center(
                  child: Material(
                    elevation: 1,
                    borderRadius: BorderRadius.circular(12),
                    clipBehavior: Clip.antiAlias,
                    child: Image.network(
                      match.paymentQrUrl!,
                      width: 220,
                      height: 220,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const SizedBox(
                        width: 220,
                        height: 120,
                        child: Center(child: Text('No pudimos cargar el QR.')),
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 24),
              MatchPlayersList(
                organizerName:
                    match.organizer?.nickname ??
                    match.organizer?.name ??
                    'Organizador',
                organizerUserId: match.organizerId,
                players: match.players ?? const [],
                isOrganizer: _isOrganizer && !_hasConcluded,
                isFull: _isFull,
                onViewPlayer: _isProcessing ? null : _showPlayerReview,
                onRemovePlayer: _isProcessing ? null : _handleRemovePlayer,
                onOpenProfile: _openPlayerProfile,
                onAddPlayer: _isProcessing
                    ? null
                    : () => _openAddPlayer(toReserve: false),
                onAddToReserve: _isProcessing
                    ? null
                    : () => _openAddPlayer(toReserve: true),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MatchActionBar extends StatelessWidget {
  const _MatchActionBar({
    required this.isJoined,
    required this.isPending,
    required this.isReserved,
    required this.isProcessing,
    required this.canJoin,
    required this.canJoinReserve,
    required this.canDelete,
    required this.canFinish,
    required this.hasConcluded,
    required this.isFinished,
    required this.isCancelled,
    required this.onShare,
    required this.onJoin,
    required this.onLeave,
    required this.onDelete,
    required this.onFinish,
  });

  final bool isJoined;
  final bool isPending;
  final bool isReserved;
  final bool isProcessing;
  final bool canJoin;
  final bool canJoinReserve;
  final bool canDelete;
  final bool canFinish;
  final bool hasConcluded;
  final bool isFinished;
  final bool isCancelled;
  final VoidCallback onShare;
  final VoidCallback onJoin;
  final VoidCallback onLeave;
  final VoidCallback onDelete;
  final VoidCallback onFinish;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final String label;
    final VoidCallback? onPressed;
    final bool outlined;

    if (isCancelled) {
      label = 'Partido cancelado';
      onPressed = null;
      outlined = true;
    } else if (isFinished) {
      label = 'Partido terminado';
      onPressed = null;
      outlined = true;
    } else if (canFinish) {
      label = isProcessing ? 'Procesando...' : 'Terminar partido';
      onPressed = isProcessing ? null : onFinish;
      outlined = false;
    } else if (hasConcluded) {
      label = 'El partido ya concluyó';
      onPressed = null;
      outlined = true;
    } else if (isReserved) {
      label = isProcessing ? 'Procesando...' : 'Salir de reserva';
      onPressed = isProcessing ? null : onLeave;
      outlined = true;
    } else if (isPending) {
      label = isProcessing ? 'Procesando...' : 'Cancelar solicitud';
      onPressed = isProcessing ? null : onLeave;
      outlined = true;
    } else if (isJoined) {
      label = isProcessing ? 'Procesando...' : 'Salir del partido';
      onPressed = isProcessing ? null : onLeave;
      outlined = true;
    } else if (canJoinReserve) {
      label = isProcessing ? 'Procesando...' : 'Unirse a reserva';
      onPressed = isProcessing ? null : onJoin;
      outlined = false;
    } else if (!canJoin) {
      label = 'Partido lleno';
      onPressed = null;
      outlined = true;
    } else {
      label = isProcessing ? 'Procesando...' : 'Unirse al equipo';
      onPressed = isProcessing ? null : onJoin;
      outlined = false;
    }

    final icon = isProcessing
        ? SizedBox.square(
            dimension: 17,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: outlined ? colors.primary : colors.onPrimary,
            ),
          )
        : Icon(
            canFinish
                ? Icons.flag_outlined
                : isJoined
                ? Icons.exit_to_app_rounded
                : Icons.group_add_outlined,
          );

    Widget primaryButton({required bool compact}) {
      final buttonStyle = compact
          ? const ButtonStyle(
              padding: WidgetStatePropertyAll(
                EdgeInsets.symmetric(horizontal: 8),
              ),
            )
          : null;
      final buttonLabel = Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      );

      if (outlined) {
        return OutlinedButton.icon(
          onPressed: onPressed,
          icon: icon,
          label: buttonLabel,
          style: buttonStyle,
        );
      }
      return FilledButton.icon(
        onPressed: onPressed,
        icon: icon,
        label: buttonLabel,
        style: buttonStyle,
      );
    }

    return Material(
      elevation: 6,
      color: colors.surface,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!isCancelled) ...[
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: OutlinedButton.icon(
                    onPressed: isProcessing ? null : onShare,
                    icon: const Icon(Icons.share_outlined),
                    label: const Text('Compartir'),
                  ),
                ),
                const SizedBox(height: 8),
              ],
              if (canDelete && !canFinish && !hasConcluded)
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: SizedBox(
                        height: 48,
                        child: primaryButton(compact: true),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: OutlinedButton.icon(
                          onPressed: isProcessing ? null : onDelete,
                          icon: Icon(
                            Icons.delete_outline_rounded,
                            size: 18,
                            color: colors.error,
                          ),
                          label: Text(
                            'Eliminar',
                            style: TextStyle(color: colors.error),
                          ),
                          style: OutlinedButton.styleFrom(
                            side: BorderSide(color: colors.error),
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                          ),
                        ),
                      ),
                    ),
                  ],
                )
              else
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: primaryButton(compact: false),
                ),
            ],
          ),
        ),
      ),
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
