import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/profile/profile_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';
import 'package:frontend/services/user_api_service.dart';

const me = UserModel(id: 1, name: 'Test User', email: 'test@example.com', phone: '72696811');

void main() {
  Future<void> pump(WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: ProfileScreen(
          user: me,
          onLogout: () {},
          userApiService: UserApiService(baseUrl: 'http://test'),
          matchApiService: _FakeMatchApi(),
          courtApiService: _FakeCourtApi(),
        ),
      ),
    );
  }

  testWidgets('profile shows the activity history entries', (tester) async {
    await pump(tester);

    expect(find.text('Mi actividad'), findsOneWidget);
    expect(find.text('Historial de reservas'), findsOneWidget);
    expect(find.text('Historial de canchas'), findsOneWidget);
    expect(find.text('Mis eventos'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Cerrar sesión'), 100);
    expect(find.text('Cerrar sesión'), findsOneWidget);
  });

  testWidgets('"Historial de reservas" lists the reservations', (tester) async {
    await pump(tester);

    await tester.tap(find.text('Historial de reservas'));
    await tester.pumpAndSettle();

    expect(find.text('Historial de reservas'), findsOneWidget); // app bar title
    expect(find.text('Arena Norte'), findsOneWidget);
    expect(find.text('Anteriores'), findsOneWidget);
  });

  testWidgets('"Historial de canchas" lists the matches the user created', (tester) async {
    await pump(tester);

    await tester.tap(find.text('Historial de canchas'));
    await tester.pumpAndSettle();

    expect(find.text('Historial de canchas'), findsOneWidget); // app bar title
    expect(find.text('Aún no has creado canchas'), findsOneWidget);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi() : super(baseUrl: 'http://test');

  @override
  Future<List<CourtReservationModel>> myReservations() async => const [
        CourtReservationModel(
          id: 1,
          bookingCode: 'BK1',
          date: '2026-09-20',
          startTime: '18:00',
          endTime: '19:00',
          hours: 1,
          amount: 50,
          status: 'paid',
          sportName: 'Fútbol 5',
          fieldName: 'Cancha 1',
          venueName: 'Arena Norte',
        ),
      ];
}

class _FakeMatchApi extends MatchApiService {
  _FakeMatchApi() : super(baseUrl: 'http://test');

  @override
  Future<List<MatchModel>> listOrganizedMatches({int page = 1}) async => const [];

  @override
  Future<PaginatedMatches> listPastOrganizedMatches({int page = 1}) async =>
      const PaginatedMatches(matches: [], currentPage: 1, lastPage: 1);
}
