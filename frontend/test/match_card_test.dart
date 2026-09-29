import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/search_teams/widgets/match_card.dart';

void main() {
  final start = DateTime.now().add(const Duration(hours: 2));

  MatchModel buildMatch({
    int missingPlayers = 4,
    MatchStatus status = MatchStatus.open,
  }) {
    return MatchModel(
      id: 1,
      organizerId: 10,
      sportId: 1,
      levelId: 1,
      courtId: 1,
      sport: const SportModel(id: 1, key: 'football', name: 'Fútbol 5'),
      level: const MatchLevelModel(
        id: 1,
        key: 'intermediate',
        name: 'Intermedio',
        order: 2,
      ),
      scheduledAt: start,
      startTime: start,
      endTime: start.add(const Duration(hours: 1)),
      totalPlayers: 10,
      missingPlayers: missingPlayers,
      maxPlayers: 10,
      status: status,
    );
  }

  Future<void> pumpCard(WidgetTester tester, MatchModel match) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MatchCard(
            match: match,
            isJoining: false,
            onJoin: () {},
          ),
        ),
      ),
    );
  }

  testWidgets('shows Unirse when the match has free slots', (tester) async {
    await pumpCard(tester, buildMatch());

    expect(find.text('Unirse'), findsOneWidget);
    expect(find.text('4 cupos'), findsOneWidget);
    expect(find.text('Unirse a reserva'), findsNothing);
  });

  testWidgets('shows Unirse a reserva when the match is full', (tester) async {
    await pumpCard(
      tester,
      buildMatch(missingPlayers: 0, status: MatchStatus.full),
    );

    expect(find.text('Unirse a reserva'), findsOneWidget);
    expect(find.text('Lleno'), findsOneWidget);
    expect(find.text('Unirse'), findsNothing);
  });
}
