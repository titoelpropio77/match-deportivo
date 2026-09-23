import 'package:flutter/material.dart';

import '../../../models/match_player_model.dart';

/// List of players registered for a match, highlighting the organizer.
class MatchPlayersList extends StatelessWidget {
  const MatchPlayersList({
    required this.organizerName,
    required this.players,
    super.key,
  });

  final String organizerName;
  final List<MatchPlayerModel> players;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Jugadores',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
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
        ),
        for (final player in players)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: CircleAvatar(
              backgroundColor: colors.secondaryContainer,
              child: Text(_avatarLetter(player)),
            ),
            title: Text(player.user?.nickname ?? player.user?.name ?? 'Jugador'),
            subtitle: player.user?.nickname != null ? Text(player.user!.name) : null,
          ),
      ],
    );
  }

  String _avatarLetter(MatchPlayerModel player) {
    final label = player.user?.nickname ?? player.user?.name ?? '?';
    return label.isNotEmpty ? label[0].toUpperCase() : '?';
  }
}
