import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/court_model.dart';
import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/create_match_screen.dart';
import 'package:frontend/screens/match_court_source.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';
import 'package:frontend/services/match_level_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';
import 'package:frontend/services/user_api_service.dart';

const arena = CourtVenueModel(id: 1, name: 'Arena Norte', address: 'Zona Norte', openingTime: '08:00:00', closingTime: '22:00:00');

String day(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

CourtReservationModel reservation({
  required int id,
  required int fieldId,
  required String field,
  required DateTime date,
  String start = '17:00',
  String end = '19:00',
  String status = 'paid',
  String code = 'BOOK1',
  CourtVenueModel venue = arena,
}) {
  return CourtReservationModel(
    id: id,
    bookingCode: code,
    date: day(date),
    startTime: start,
    endTime: end,
    hours: int.parse(end.split(':')[0]) - int.parse(start.split(':')[0]),
    amount: 100,
    status: status,
    sportName: 'Pádel',
    fieldName: field,
    venueName: venue.name,
    paymentExpiresAt: DateTime.now().add(const Duration(minutes: 10)),
    field: CourtFieldModel(id: fieldId, name: field, pricePerHour: 50, venue: venue),
  );
}

void main() {
  final tomorrow = DateUtils.dateOnly(DateTime.now()).add(const Duration(days: 1));

  test('reservations become blocks per venue and day, merging courts and consecutive hours', () {
    final slots = ReservedSlot.fromReservations([
      reservation(id: 1, fieldId: 11, field: 'Cancha 1', date: tomorrow),
      reservation(id: 2, fieldId: 12, field: 'Cancha 2', date: tomorrow),
      reservation(id: 3, fieldId: 11, field: 'Cancha 1', date: tomorrow, start: '19:00', end: '20:00'),
      reservation(id: 4, fieldId: 11, field: 'Cancha 1', date: tomorrow, start: '09:00', end: '10:00', code: 'B2'),
      reservation(id: 5, fieldId: 11, field: 'Cancha 1', date: tomorrow.add(const Duration(days: 1)), code: 'B3'),
      reservation(id: 6, fieldId: 11, field: 'Cancha 1', date: tomorrow, start: '12:00', end: '13:00', status: 'cancelled'),
      reservation(id: 7, fieldId: 11, field: 'Cancha 1', date: tomorrow.subtract(const Duration(days: 3))),
    ]);

    expect(slots.map((slot) => '${slot.start.day} ${slot.start.hour}-${slot.end.hour} ${slot.fieldNames.join('+')}'), [
      '${tomorrow.day} 9-10 Cancha 1',
      '${tomorrow.day} 17-20 Cancha 1+Cancha 2',
      '${tomorrow.add(const Duration(days: 1)).day} 17-19 Cancha 1',
    ]);
    expect(slots[1].fieldIds, {11, 12});
  });

  Future<_FakeCourtApi> pump(WidgetTester tester, List<List<CourtReservationModel>> answers) async {
    tester.view.physicalSize = const Size(800, 2800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final api = _FakeCourtApi(answers);
    await tester.pumpWidget(
      MaterialApp(
        home: CreateMatchScreen(
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          userApiService: UserApiService(baseUrl: 'http://test'),
          sportApiService: _FakeSportApi(),
          matchLevelApiService: _FakeLevelApi(),
          courtApiService: api,
        ),
      ),
    );
    await tester.pumpAndSettle();
    return api;
  }

  testWidgets('picking an app reservation fills venue, courts, sport and time', (tester) async {
    await pump(tester, [
      [
        reservation(id: 1, fieldId: 11, field: 'Cancha 1', date: tomorrow),
        reservation(id: 2, fieldId: 12, field: 'Cancha 2', date: tomorrow),
      ],
    ]);

    expect(find.text('¿Ya tienes la cancha reservada?'), findsOneWidget);
    // Until the user answers, the form works as before.
    expect(find.text('Centro / complejo deportivo'), findsOneWidget);

    await tester.tap(find.text('Sí, la reservé en la app'));
    await tester.pumpAndSettle();

    expect(find.text('Centro / complejo deportivo'), findsNothing);
    expect(find.text('Elige la reserva para el partido'), findsOneWidget);
    expect(find.textContaining('17:00–19:00\nCancha 1, Cancha 2'), findsOneWidget);

    await tester.tap(find.text('Arena Norte'));
    await tester.pumpAndSettle();

    expect(find.text('Cancha reservada'), findsOneWidget);
    expect(find.text('Cancha 1, Cancha 2'), findsOneWidget);
    expect(find.textContaining('17:00–19:00'), findsOneWidget);
    expect(find.text('¿Cuándo se juega?'), findsNothing);
    // Sport taken from the reservation.
    expect(find.text('Pádel'), findsOneWidget);

    await tester.tap(find.text('Cambiar'));
    await tester.pumpAndSettle();
    expect(find.text('Elige la reserva para el partido'), findsOneWidget);

    await tester.tap(find.text('Ya la reservé por mi cuenta'));
    await tester.pumpAndSettle();
    expect(find.text('Centro / complejo deportivo'), findsOneWidget);
    expect(find.text('¿Cuándo se juega?'), findsOneWidget);
  });

  testWidgets('opened from a booking it starts with that reservation chosen', (tester) async {
    tester.view.physicalSize = const Size(800, 2800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        home: CreateMatchScreen(
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          userApiService: UserApiService(baseUrl: 'http://test'),
          sportApiService: _FakeSportApi(),
          matchLevelApiService: _FakeLevelApi(),
          courtApiService: _FakeCourtApi([
            [
              reservation(id: 1, fieldId: 11, field: 'Cancha 1', date: tomorrow, start: '09:00', end: '10:00', code: 'OTHER'),
              reservation(id: 2, fieldId: 12, field: 'Cancha 2', date: tomorrow, code: 'BK1'),
            ],
          ]),
          initialBookingCode: 'BK1',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Cancha reservada'), findsOneWidget);
    expect(find.text('Cancha 2'), findsOneWidget);
    expect(find.textContaining('17:00–19:00'), findsOneWidget);
    expect(find.text('Centro / complejo deportivo'), findsNothing);
  });

  test('creating a match sends the booking it was made from', () async {
    String? body;
    final client = MockClient((request) async {
      body = request.body;
      return http.Response(
        '{"data":{"id":1,"organizer_id":1,"sport_id":1,"level_id":1,"court_id":1,"booking_code":"BK1",'
        '"scheduled_at":"2026-10-02T17:00:00Z","start_time":"2026-10-02T17:00:00Z",'
        '"end_time":"2026-10-02T19:00:00Z","total_players":10,"missing_players":10,'
        '"max_players":10,"status":"open"}}',
        201,
      );
    });

    await MatchApiService(baseUrl: 'http://test', client: client).createMatch(
      sportId: 1,
      levelId: 1,
      courtId: 1,
      gender: 'mixed',
      startTime: DateTime(2026, 10, 2, 17),
      endTime: DateTime(2026, 10, 2, 19),
      maxPlayers: 10,
      bookingCode: 'BK1',
    );

    expect(body, contains('name="booking_code"\r\n\r\nBK1'));
  });

  testWidgets('without app reservations it offers to book one', (tester) async {
    await pump(tester, [const []]);

    await tester.tap(find.text('Sí, la reservé en la app'));
    await tester.pumpAndSettle();

    expect(find.text('No tienes reservas próximas en la app.'), findsOneWidget);
    expect(find.text('Reservar cancha ahora'), findsOneWidget);
  });

  testWidgets('booking now comes back and fills the form with the new reservation', (tester) async {
    final api = await pump(tester, [
      const [],
      [reservation(id: 9, fieldId: 11, field: 'Cancha 1', date: tomorrow, code: 'BNEW')],
    ]);

    await tester.tap(find.text('No, quiero reservarla ahora'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Reservar cancha'));
    await tester.pumpAndSettle();

    // The booking flow is open; simulate paying (the payment screen pops it with `true`).
    expect(find.text('Reservar cancha'), findsWidgets);
    tester.state<NavigatorState>(find.byType(Navigator)).pop(true);
    await tester.pumpAndSettle();

    expect(api.reservationLoads, 2);
    expect(find.text('Cancha reservada'), findsOneWidget);
    expect(find.text('Cancha 1'), findsOneWidget);
    expect(find.text('Reserva lista: completamos la cancha y el horario.'), findsOneWidget);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi(this.answers) : super(baseUrl: 'http://test');

  /// One answer per call to myReservations (the last one repeats).
  final List<List<CourtReservationModel>> answers;
  int reservationLoads = 0;

  @override
  Future<List<CourtReservationModel>> myReservations() async {
    final answer = answers[reservationLoads.clamp(0, answers.length - 1)];
    reservationLoads++;
    return answer;
  }

  @override
  Future<List<CourtModel>> list({String? search}) async => const [
        CourtModel(
          id: 1,
          name: 'Arena Norte',
          address: 'Zona Norte',
          openingTime: '08:00:00',
          closingTime: '22:00:00',
          fields: [
            CourtFieldOption(id: 11, name: 'Cancha 1', sports: [SportModel(id: 1, key: 'padel', name: 'Pádel')]),
            CourtFieldOption(id: 12, name: 'Cancha 2', sports: [SportModel(id: 1, key: 'padel', name: 'Pádel')]),
            CourtFieldOption(id: 13, name: 'Cancha 3'),
          ],
        ),
      ];

  @override
  Future<List<CourtFieldModel>> listFields({int? sportId, DateTime? date}) async => const [];
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 2, key: 'baloncesto', name: 'Baloncesto'),
        SportModel(id: 1, key: 'padel', name: 'Pádel'),
      ];
}

class _FakeLevelApi extends MatchLevelApiService {
  _FakeLevelApi() : super(baseUrl: 'http://test');

  @override
  Future<List<MatchLevelModel>> list() async => const [MatchLevelModel(id: 1, key: 'basic', name: 'Básico', order: 1)];
}
