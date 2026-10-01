import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/court_model.dart';
import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/create_match_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';
import 'package:frontend/services/match_level_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';
import 'package:frontend/services/user_api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<void> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: CreateMatchScreen(
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          userApiService: UserApiService(baseUrl: 'http://test'),
          sportApiService: _FakeSportApiService(),
          matchLevelApiService: _FakeMatchLevelApiService(),
          courtApiService: _FakeCourtApiService(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // The courts only appear once a sports center is searched and chosen.
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Centro / complejo deportivo'),
      'arena',
    );
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Arena Norte').last);
    await tester.pumpAndSettle();
  }

  testWidgets('labels the venue as sports center and lists its courts', (tester) async {
    await pumpScreen(tester);

    expect(find.text('Centro / complejo deportivo'), findsOneWidget);
    expect(find.text('Lugar / Cancha'), findsNothing);
    expect(find.text('Canchas'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Cancha 1 (Raquet)'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Cancha 2 (Pádel)'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Cancha 3 (Wally)'), findsOneWidget);
  });

  testWidgets('allows selecting several courts', (tester) async {
    await pumpScreen(tester);

    await tester.tap(find.widgetWithText(FilterChip, 'Cancha 1 (Raquet)'));
    await tester.tap(find.widgetWithText(FilterChip, 'Cancha 3 (Wally)'));
    await tester.pumpAndSettle();

    bool isSelected(String label) =>
        tester.widget<FilterChip>(find.widgetWithText(FilterChip, label)).selected;

    expect(isSelected('Cancha 1 (Raquet)'), isTrue);
    expect(isSelected('Cancha 2 (Pádel)'), isFalse);
    expect(isSelected('Cancha 3 (Wally)'), isTrue);

    await tester.tap(find.widgetWithText(FilterChip, 'Cancha 1 (Raquet)'));
    await tester.pumpAndSettle();
    expect(isSelected('Cancha 1 (Raquet)'), isFalse);
  });

  test('parses the courts of a match from the API', () {
    final match = MatchModel.fromJson({
      'id': 10,
      'organizer_id': 1,
      'sport_id': 1,
      'level_id': 1,
      'court_id': 1,
      'scheduled_at': '2026-10-01T18:00:00Z',
      'start_time': '2026-10-01T18:00:00Z',
      'end_time': '2026-10-01T20:00:00Z',
      'total_players': 8,
      'missing_players': 8,
      'max_players': 8,
      'status': 'open',
      'court_fields': [
        {
          'id': 5,
          'name': 'Cancha 2',
          'sports': [
            {'id': 3, 'key': 'padel', 'name': 'Pádel'},
          ],
        },
      ],
    });

    expect(match.courtFields.single.label, 'Cancha 2 (Pádel)');
  });
}

class _FakeSportApiService extends SportApiService {
  _FakeSportApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'padel', name: 'Pádel'),
      ];
}

class _FakeMatchLevelApiService extends MatchLevelApiService {
  _FakeMatchLevelApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<MatchLevelModel>> list() async => const [
        MatchLevelModel(id: 1, key: 'basic', name: 'Básico', order: 1),
      ];
}

class _FakeCourtApiService extends CourtApiService {
  _FakeCourtApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<CourtModel>> list({String? search}) async => const [
        CourtModel(
          id: 1,
          name: 'Arena Norte',
          address: 'Zona Norte',
          fields: [
            CourtFieldOption(
              id: 11,
              name: 'Cancha 1',
              sports: [SportModel(id: 7, key: 'racquetball', name: 'Raquet')],
            ),
            CourtFieldOption(
              id: 12,
              name: 'Cancha 2',
              sports: [SportModel(id: 3, key: 'padel', name: 'Pádel')],
            ),
            CourtFieldOption(
              id: 13,
              name: 'Cancha 3',
              sports: [SportModel(id: 8, key: 'wallyball', name: 'Wally')],
            ),
          ],
        ),
      ];
}
