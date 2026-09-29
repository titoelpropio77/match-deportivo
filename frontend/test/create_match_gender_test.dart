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

  Future<void> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1600);
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
  }

  testWidgets('shows mixto, masculino and femenino gender options', (tester) async {
    await pumpScreen(tester);

    expect(find.text('Género'), findsOneWidget);
    expect(find.text('Mixto'), findsOneWidget);

    await tester.ensureVisible(find.text('Mixto'));
    await tester.tap(find.text('Mixto'));
    await tester.pumpAndSettle();

    expect(find.text('Mixto'), findsWidgets);
    expect(find.text('Masculino'), findsOneWidget);
    expect(find.text('Femenino'), findsOneWidget);
  });

  testWidgets('shows the payment QR photo picker', (tester) async {
    await pumpScreen(tester);

    expect(find.text('QR de cobro'), findsOneWidget);
    expect(find.text('Subir foto del QR'), findsOneWidget);
    expect(find.text('Elegir de la galería'), findsOneWidget);
  });
}

class _FakeSportApiService extends SportApiService {
  _FakeSportApiService() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'basketball', name: 'Baloncesto'),
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
  Future<List<CourtModel>> list() async => const [
        CourtModel(id: 1, name: 'Arena Norte', address: 'Zona Norte'),
      ];
}
