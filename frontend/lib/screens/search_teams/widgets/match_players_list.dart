import 'package:flutter/material.dart';

import '../../../models/match_player_model.dart';

/// List of players registered for a match, highlighting the organizer.
class MatchPlayersList extends StatelessWidget {
  const MatchPlayersList({
    required this.organizerName,
    required this.players,
    this.organizerUserId,
    this.isOrganizer = false,
    this.isFull = false,
    this.onViewPlayer,
    this.onRemovePlayer,
    this.onOpenProfile,
    this.onAddPlayer,
    this.onAddToReserve,
    super.key,
  });

  final String organizerName;
  final int? organizerUserId;
  final List<MatchPlayerModel> players;
  final bool isOrganizer;
  final bool isFull;
  final ValueChanged<MatchPlayerModel>? onViewPlayer;
  final ValueChanged<MatchPlayerModel>? onRemovePlayer;

  /// Opens the player's profile (receives the user id).
  final ValueChanged<int>? onOpenProfile;
  final VoidCallback? onAddPlayer;
  final VoidCallback? onAddToReserve;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final activePlayers = players
        .where((player) => !player.isReserved && player.userId != organizerUserId)
        .toList();
    final reservedPlayers = players.where((player) => player.isReserved).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Jugadores',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ),
            if (isOrganizer && !isFull)
              TextButton.icon(
                onPressed: onAddPlayer,
                icon: const Icon(Icons.person_add_alt_1_outlined, size: 18),
                label: const Text('Agregar jugador'),
              ),
          ],
        ),
        const SizedBox(height: 8),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: CircleAvatar(
            backgroundColor: colors.primaryContainer,
            child: Text(organizerName.isNotEmpty ? organizerName[0].toUpperCase() : '?'),
          ),
          title: Text(organizerName),
          subtitle: const Text('Organizador'),
          onTap: organizerUserId == null || onOpenProfile == null ? null : () => onOpenProfile!(organizerUserId!),
        ),
        for (final player in activePlayers)
          _PlayerTile(
            player: player,
            isOrganizer: isOrganizer,
            onViewPlayer: onViewPlayer,
            onRemovePlayer: onRemovePlayer,
            onOpenProfile: onOpenProfile,
          ),
        if (isFull || reservedPlayers.isNotEmpty) ...[
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Jugadores en reserva',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              if (isOrganizer && isFull)
                TextButton.icon(
                  onPressed: onAddToReserve,
                  icon: const Icon(Icons.playlist_add_outlined, size: 18),
                  label: const Text('Agregar a reserva'),
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (reservedPlayers.isEmpty)
            Text(
              'Nadie está en reserva todavía.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
            )
          else
            for (final entry in reservedPlayers.asMap().entries)
              _PlayerTile(
                player: entry.value,
                isOrganizer: isOrganizer,
                reservePosition: entry.key + 1,
                onViewPlayer: onViewPlayer,
                onRemovePlayer: onRemovePlayer,
                onOpenProfile: onOpenProfile,
              ),
        ],
      ],
    );
  }
}

class _PlayerTile extends StatelessWidget {
  const _PlayerTile({
    required this.player,
    required this.isOrganizer,
    this.reservePosition,
    this.onViewPlayer,
    this.onRemovePlayer,
    this.onOpenProfile,
  });

  final MatchPlayerModel player;
  final bool isOrganizer;
  final int? reservePosition;
  final ValueChanged<MatchPlayerModel>? onViewPlayer;
  final ValueChanged<MatchPlayerModel>? onRemovePlayer;
  final ValueChanged<int>? onOpenProfile;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final label = player.user?.nickname ?? player.user?.name ?? 'Jugador';

    final userId = player.user?.id;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      onTap: userId == null || onOpenProfile == null ? null : () => onOpenProfile!(userId),
      leading: CircleAvatar(
        backgroundColor: player.isReserved ? colors.tertiaryContainer : colors.secondaryContainer,
        child: Text(label.isNotEmpty ? label[0].toUpperCase() : '?'),
      ),
      title: Text(label),
      subtitle: Text(
        player.isReserved
            ? 'Reserva #${reservePosition ?? '-'}'
            : player.isPending
                ? 'Pendiente de confirmación'
                : (player.user?.nickname != null ? player.user!.name : 'Confirmado'),
      ),
      trailing: isOrganizer
          ? player.isPending
              ? TextButton(
                  onPressed: onViewPlayer == null ? null : () => onViewPlayer!(player),
                  child: const Text('Ver jugador'),
                )
              : TextButton(
                  onPressed: onRemovePlayer == null ? null : () => onRemovePlayer!(player),
                  child: const Text('Quitar jugador'),
                )
          : null,
    );
  }
}
