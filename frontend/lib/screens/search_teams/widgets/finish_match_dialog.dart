import 'package:flutter/material.dart';

import '../../../models/match_player_model.dart';
import '../../../models/rating_tag_model.dart';

/// Ratings the organizer chose before finishing a match.
class PlayerRatingInput {
  const PlayerRatingInput({
    required this.userId,
    this.stars,
    this.didNotAttend = false,
    this.tagIds = const [],
  });

  final int userId;
  final int? stars;
  final bool didNotAttend;
  final List<int> tagIds;

  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'did_not_attend': didNotAttend,
      if (!didNotAttend && stars != null) 'stars': stars,
      if (!didNotAttend) 'tag_ids': tagIds,
    };
  }
}

/// Optional player ratings shown after the organizer confirms the match is over.
class FinishMatchDialog extends StatefulWidget {
  const FinishMatchDialog({
    required this.players,
    required this.tags,
    super.key,
  });

  final List<MatchPlayerModel> players;
  final List<RatingTagModel> tags;

  @override
  State<FinishMatchDialog> createState() => _FinishMatchDialogState();
}

class _FinishMatchDialogState extends State<FinishMatchDialog> {
  late final Map<int, _Draft> _drafts = {
    for (final player in widget.players) player.userId: _Draft(),
  };

  List<PlayerRatingInput> _collected() {
    return [
      for (final player in widget.players)
        if (_drafts[player.userId]!.hasInput)
          PlayerRatingInput(
            userId: player.userId,
            stars: _drafts[player.userId]!.stars,
            didNotAttend: _drafts[player.userId]!.didNotAttend,
            tagIds: _drafts[player.userId]!.tagIds.toList(),
          ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Calificar jugadores'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'La calificación es opcional. Puedes terminar el partido sin puntuar a nadie.',
              ),
              const SizedBox(height: 16),
              if (widget.players.isEmpty)
                const Text('No hay jugadores confirmados para calificar.')
              else
                for (final player in widget.players) ...[
                  _PlayerRatingEditor(
                    player: player,
                    tags: widget.tags,
                    draft: _drafts[player.userId]!,
                    onChanged: () => setState(() {}),
                  ),
                  const SizedBox(height: 16),
                ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancelar'),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).pop(const <PlayerRatingInput>[]),
          child: const Text('Omitir calificaciones'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_collected()),
          child: const Text('Terminar partido'),
        ),
      ],
    );
  }
}

class _Draft {
  int? stars;
  bool didNotAttend = false;
  final Set<int> tagIds = {};

  bool get hasInput => didNotAttend || stars != null;
}

class _PlayerRatingEditor extends StatelessWidget {
  const _PlayerRatingEditor({
    required this.player,
    required this.tags,
    required this.draft,
    required this.onChanged,
  });

  final MatchPlayerModel player;
  final List<RatingTagModel> tags;
  final _Draft draft;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final name = player.user?.nickname ?? player.user?.name ?? 'Jugador';
    final visibleTags = draft.stars == null || draft.stars == 3
        ? const <RatingTagModel>[]
        : tags.where((tag) {
            if (draft.stars! < 3) return tag.isNegative;
            return tag.isPositive;
          }).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(name, style: Theme.of(context).textTheme.titleSmall),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: draft.didNotAttend,
          title: const Text('No vino a jugar'),
          controlAffinity: ListTileControlAffinity.leading,
          onChanged: (value) {
            draft.didNotAttend = value ?? false;
            if (draft.didNotAttend) {
              draft.stars = null;
              draft.tagIds.clear();
            }
            onChanged();
          },
        ),
        if (!draft.didNotAttend) ...[
          Row(
            children: [
              for (var star = 1; star <= 5; star++)
                IconButton(
                  tooltip: '$star estrellas',
                  visualDensity: VisualDensity.compact,
                  onPressed: () {
                    draft.stars = star;
                    draft.tagIds.clear();
                    onChanged();
                  },
                  icon: Icon(
                    draft.stars != null && star <= draft.stars!
                        ? Icons.star_rounded
                        : Icons.star_outline_rounded,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
            ],
          ),
          if (visibleTags.isNotEmpty)
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                for (final tag in visibleTags)
                  FilterChip(
                    label: Text(tag.label),
                    selected: draft.tagIds.contains(tag.id),
                    onSelected: (selected) {
                      if (selected) {
                        draft.tagIds.add(tag.id);
                      } else {
                        draft.tagIds.remove(tag.id);
                      }
                      onChanged();
                    },
                  ),
              ],
            ),
        ],
      ],
    );
  }
}
