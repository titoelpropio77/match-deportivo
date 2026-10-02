import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/ranking_model.dart';
import 'package:frontend/screens/ranking/ranking_screen.dart';
import 'package:frontend/services/ranking_api_service.dart';

void main() {
  late _FakeRankingApiService service;

  setUp(() => service = _FakeRankingApiService());

  Future<void> pump(WidgetTester tester, {void Function(RankingEntry)? onCourt}) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(MaterialApp(
      home: RankingScreen(rankingApiService: service, onCourtPressed: onCourt),
    ));
    await tester.pumpAndSettle();
  }

  testWidgets('shows centers first, ordered, with their metric', (tester) async {
    await pump(tester);

    expect(find.text('Complejo Wally Sur'), findsOneWidget);
    expect(find.text('12'), findsOneWidget);
    expect(find.text('reservas'), findsOneWidget);
    expect(find.textContaining('★ 4.8 (32)'), findsOneWidget);
    expect(find.byIcon(Icons.emoji_events), findsNWidgets(2));
  });

  testWidgets('teams tab shows points and the record', (tester) async {
    await pump(tester);

    await tester.tap(find.text('Equipos'));
    await tester.pumpAndSettle();

    expect(find.text('Los Tigres'), findsOneWidget);
    expect(find.text('9'), findsOneWidget);
    expect(find.text('pts'), findsOneWidget);
    expect(find.textContaining('PJ 5 · 3G 0E 2P · DG +2'), findsOneWidget);
    expect(find.text('TIG'), findsOneWidget);
  });

  testWidgets('switching to this month reloads and shows the empty state', (tester) async {
    await pump(tester);

    await tester.tap(find.text('Este mes'));
    await tester.pumpAndSettle();
    expect(service.lastPeriod, RankingPeriod.month);

    await tester.tap(find.text('Productos'));
    await tester.pumpAndSettle();
    expect(find.text('No se vendieron productos este mes.'), findsOneWidget);
  });

  testWidgets('tapping a center reports it', (tester) async {
    RankingEntry? opened;
    await pump(tester, onCourt: (court) => opened = court);

    await tester.tap(find.text('Complejo Wally Sur'));
    expect(opened?.id, 1);
  });

  testWidgets('shows an error with retry', (tester) async {
    service.fail = true;
    await pump(tester);

    expect(find.text('No pudimos cargar el ranking.'), findsOneWidget);
    service.fail = false;
    await tester.tap(find.text('Reintentar'));
    await tester.pumpAndSettle();
    expect(find.text('Complejo Wally Sur'), findsOneWidget);
  });
}

class _FakeRankingApiService extends RankingApiService {
  _FakeRankingApiService() : super(baseUrl: 'http://test');

  RankingPeriod? lastPeriod;
  bool fail = false;

  @override
  Future<RankingModel> fetch({RankingPeriod period = RankingPeriod.all, int? cityId}) async {
    lastPeriod = period;
    if (fail) throw const RankingApiException('boom', 500);

    return RankingModel(
      period: period,
      courts: const [
        RankingEntry(id: 1, name: 'Complejo Wally Sur', subtitle: 'Santa Cruz', bookings: 12, rating: 4.8, reviewsCount: 32),
        RankingEntry(id: 2, name: 'Arena Norte', bookings: 1),
      ],
      teams: const [
        RankingEntry(id: 5, name: 'Los Tigres', shortName: 'TIG', color: '#F59E0B', played: 5, won: 3, lost: 2, goalDifference: 2, points: 9),
      ],
      products: period == RankingPeriod.all
          ? const [RankingEntry(id: 9, name: 'Balón', unitsSold: 3, price: 150)]
          : const [],
    );
  }
}
