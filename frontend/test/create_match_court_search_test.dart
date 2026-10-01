import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/court_model.dart';
import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/create_match_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';
import 'package:frontend/services/match_level_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';
import 'package:frontend/services/user_api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final searchField = find.widgetWithText(TextFormField, 'Centro / complejo deportivo');
  late _FakeCourtApiService courtApi;

  Future<void> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    courtApi = _FakeCourtApiService();
    await tester.pumpWidget(
      MaterialApp(
        home: CreateMatchScreen(
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          userApiService: UserApiService(baseUrl: 'http://test'),
          sportApiService: _FakeSportApiService(),
          matchLevelApiService: _FakeMatchLevelApiService(),
          courtApiService: courtApi,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> search(WidgetTester tester, String text) async {
    await tester.enterText(searchField, text);
    // Past the search debounce, then let the API answer and the overlay render.
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();
  }

  /// Names of the centers listed in the suggestions overlay.
  final optionTiles = find.descendant(
    of: find.byKey(const Key('court-search-options')),
    matching: find.byType(ListTile),
  );

  List<String> suggestions(WidgetTester tester) => tester
      .widgetList<ListTile>(optionTiles)
      .map((tile) => (tile.title! as Text).data!)
      .toList();

  testWidgets('starts with no center selected and does not preload centers', (tester) async {
    await pumpScreen(tester);

    expect(tester.widget<TextFormField>(searchField).controller!.text, isEmpty);
    expect(find.byType(FilterChip), findsNothing);
    expect(courtApi.searches, isEmpty);
  });

  testWidgets('searches the centers on the API with the typed text', (tester) async {
    await pumpScreen(tester);

    await search(tester, 'cochabamba');
    expect(courtApi.searches, ['cochabamba']);
    expect(suggestions(tester), ['Frontón Central']);

    await search(tester, 'wally');
    expect(courtApi.searches.last, 'wally');
    expect(suggestions(tester), ['Complejo Wally Sur']);
  });

  testWidgets('only the text left after a pause is searched', (tester) async {
    await pumpScreen(tester);

    await tester.enterText(searchField, 'a');
    await tester.pump(const Duration(milliseconds: 100));
    await tester.enterText(searchField, 'ar');
    await tester.pump(const Duration(milliseconds: 100));
    await search(tester, 'arena');

    expect(courtApi.searches, ['arena']);
    expect(suggestions(tester), ['Arena Norte']);
  });

  testWidgets('selecting a center shows its courts and clearing the text drops it', (tester) async {
    await pumpScreen(tester);

    await search(tester, 'wally');
    await tester.tap(find.descendant(of: optionTiles, matching: find.text('Complejo Wally Sur')));
    await tester.pumpAndSettle();

    expect(tester.widget<TextFormField>(searchField).controller!.text, 'Complejo Wally Sur');
    expect(find.widgetWithText(FilterChip, 'Cancha Wally (Wally)'), findsOneWidget);

    await search(tester, '');
    expect(find.byType(FilterChip), findsNothing);

    final submit = find.widgetWithText(FilledButton, 'Crear partido');
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(
      find.text('Selecciona el deporte, el nivel y el centro deportivo.'),
      findsOneWidget,
    );
  });

  testWidgets('tapping the chosen center selects its name to search again', (tester) async {
    await pumpScreen(tester);

    await search(tester, 'arena');
    await tester.tap(find.descendant(of: optionTiles, matching: find.text('Arena Norte')));
    await tester.pumpAndSettle();

    await tester.tap(searchField);
    await tester.pumpAndSettle();
    final controller = tester.widget<TextFormField>(searchField).controller!;
    expect(controller.selection, const TextSelection(baseOffset: 0, extentOffset: 11));
  });

  testWidgets('tells the user when nothing matches', (tester) async {
    await pumpScreen(tester);

    await search(tester, 'zzz');
    expect(optionTiles, findsNothing);
    expect(find.text('No se encontraron centros deportivos.'), findsOneWidget);
  });

  testWidgets('tells the user when the search fails', (tester) async {
    await pumpScreen(tester);
    courtApi.fail = true;

    await search(tester, 'arena');
    expect(optionTiles, findsNothing);
    expect(
      find.text('No pudimos buscar centros deportivos. Intenta de nuevo.'),
      findsOneWidget,
    );
  });

  test('reads the city name from the API', () {
    final court = CourtModel.fromJson({
      'id': 1,
      'name': 'Arena Norte',
      'address': 'Zona Norte',
      'city': {'id': 1, 'name': 'Santa Cruz de la Sierra'},
    });

    expect(court.cityName, 'Santa Cruz de la Sierra');
  });
}

class _FakeSportApiService extends SportApiService {
  _FakeSportApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'wally', name: 'Wally'),
      ];
}

class _FakeMatchLevelApiService extends MatchLevelApiService {
  _FakeMatchLevelApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<MatchLevelModel>> list() async => const [
        MatchLevelModel(id: 1, key: 'basic', name: 'Básico', order: 1),
      ];
}

/// Stands in for `GET /api/courts?search=`: records each search and filters like the server.
class _FakeCourtApiService extends CourtApiService {
  _FakeCourtApiService() : super(baseUrl: 'http://test');

  final List<String> searches = [];
  bool fail = false;

  static const _courts = [
    CourtModel(
      id: 1,
      name: 'Arena Norte',
      address: 'Zona Norte',
      cityName: 'Santa Cruz de la Sierra',
      fields: [CourtFieldOption(id: 11, name: 'Cancha 1')],
    ),
    CourtModel(
      id: 2,
      name: 'Complejo Wally Sur',
      address: 'Av. Equipetrol',
      cityName: 'Santa Cruz de la Sierra',
      fields: [
        CourtFieldOption(
          id: 21,
          name: 'Cancha Wally',
          sports: [SportModel(id: 1, key: 'wally', name: 'Wally')],
        ),
      ],
    ),
    CourtModel(
      id: 3,
      name: 'Frontón Central',
      address: 'Calle España',
      cityName: 'Cochabamba',
    ),
  ];

  @override
  Future<List<CourtModel>> list({String? search}) async {
    final query = (search ?? '').trim().toLowerCase();
    if (query.isNotEmpty) searches.add(query);
    if (fail) throw const CourtApiException('Server error', 500);
    return _courts
        .where(
          (court) => [court.name, court.address, court.cityName ?? '']
              .any((value) => value.toLowerCase().contains(query)),
        )
        .toList();
  }
}
