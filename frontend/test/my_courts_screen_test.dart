import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/court_model.dart';
import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/home/widgets/my_courts_section.dart';
import 'package:frontend/screens/my_courts/my_courts_screen.dart';
import 'package:frontend/services/match_api_service.dart';

void main() {
  final start = DateTime.utc(2026, 9, 23, 22, 26);
  final end = DateTime.utc(2026, 9, 23, 23, 10);

  MatchModel buildMatch() {
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
      court: const CourtModel(
        id: 1,
        name: 'Arena Norte',
        address: 'Zona Norte, Santa Cruz',
      ),
      scheduledAt: start,
      startTime: start,
      endTime: end,
      totalPlayers: 10,
      missingPlayers: 9,
      maxPlayers: 10,
      status: MatchStatus.open,
    );
  }

  testWidgets('home section shows Mis canchas and is tappable', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MyCourtsSection(onTap: () => tapped = true),
        ),
      ),
    );

    expect(find.text('Mis canchas'), findsOneWidget);
    expect(find.text('Ver los partidos que creaste'), findsOneWidget);

    await tester.tap(find.text('Mis canchas'));
    expect(tapped, isTrue);
  });

  testWidgets('lists matches created by the user', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MyCourtsScreen(
          matchApiService: _FakeMatchApiService([buildMatch()]),
          currentUserId: 10,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mis canchas'), findsOneWidget);
    expect(find.text('Fútbol 5'), findsOneWidget);
    expect(find.text('Arena Norte'), findsOneWidget);
    expect(find.text('Tú lo creaste'), findsOneWidget);
    expect(find.text('Crear cancha'), findsOneWidget);
    expect(find.text('Activas'), findsOneWidget);
    expect(find.text('Pasadas'), findsOneWidget);
    expect(find.text('No tienes canchas pasadas.'), findsOneWidget);
  });

  testWidgets('shows past matches in a paged section', (tester) async {
    final past = buildMatch();
    await tester.pumpWidget(
      MaterialApp(
        home: MyCourtsScreen(
          matchApiService: _FakeMatchApiService(
            const [],
            pastMatches: [past],
            pastLastPage: 2,
          ),
          currentUserId: 10,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('No tienes canchas activas.'), findsOneWidget);
    expect(find.text('Finalizada'), findsOneWidget);
    expect(find.text('1 / 2'), findsOneWidget);
  });

  testWidgets('shows an empty state when the user has no matches', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MyCourtsScreen(
          matchApiService: _FakeMatchApiService(const []),
          currentUserId: 10,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Aún no has creado canchas'), findsOneWidget);
    expect(find.text('Crear cancha'), findsWidgets);
  });
}

class _FakeMatchApiService extends MatchApiService {
  _FakeMatchApiService(
    this.matches, {
    this.pastMatches = const [],
    this.pastLastPage = 1,
  }) : super(baseUrl: 'http://test');

  final List<MatchModel> matches;
  final List<MatchModel> pastMatches;
  final int pastLastPage;
  int requestedPastPage = 1;

  @override
  Future<List<MatchModel>> listOrganizedMatches({int page = 1}) async => matches;

  @override
  Future<PaginatedMatches> listPastOrganizedMatches({int page = 1}) async {
    requestedPastPage = page;
    return PaginatedMatches(
      matches: pastMatches,
      currentPage: page,
      lastPage: pastLastPage,
    );
  }
}
