import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/screens/home/widgets/my_reservations_section.dart';
import 'package:frontend/screens/my_reservations/my_reservations_screen.dart';
import 'package:frontend/screens/create_match_screen.dart';
import 'package:frontend/screens/search_teams/match_detail_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';

String _day(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

CourtReservationModel _reservation({
  required int id,
  required DateTime date,
  required String status,
  String venue = 'Canchas El Torneo',
  String? cancellationReason,
  bool wasPaid = false,
  String? bookingCode,
  String field = 'Cancha A',
  String start = '18:00',
  String end = '19:00',
  int? matchId,
}) {
  return CourtReservationModel(
    id: id,
    bookingCode: bookingCode,
    matchId: matchId,
    date: _day(date),
    startTime: start,
    endTime: end,
    hours: 1,
    amount: 80,
    status: status,
    sportName: 'Fútbol 5',
    fieldName: field,
    venueName: venue,
    paymentReference: bookingCode ?? 'MD-${id.toString().padLeft(6, '0')}',
    paymentExpiresAt: DateTime.now().add(const Duration(minutes: 10)),
    cancellationReason: cancellationReason,
    cancelledByVenue: cancellationReason != null,
    wasPaid: wasPaid,
  );
}

void main() {
  final tomorrow = DateTime.now().add(const Duration(days: 1));
  final lastWeek = DateTime.now().subtract(const Duration(days: 7));

  testWidgets('home entry shows the next reservation', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MyReservationsSection(
            reservations: [_reservation(id: 1, date: tomorrow, status: 'paid')],
            onTap: () {},
          ),
        ),
      ),
    );

    expect(find.text('Mis reservas (1 próxima)'), findsOneWidget);
    expect(find.textContaining('Próxima: Canchas El Torneo'), findsOneWidget);
  });

  testWidgets('lists reservations and opens the detail to complete a pending payment', (tester) async {
    final api = _FakeCourtApi([
      _reservation(id: 2, date: tomorrow, status: 'pending_payment'),
      _reservation(id: 1, date: lastWeek, status: 'paid', venue: 'Arena Norte'),
    ]);

    await tester.pumpWidget(MaterialApp(home: MyReservationsScreen(courtApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Próximas'), findsOneWidget);
    expect(find.text('Anteriores'), findsOneWidget);
    expect(find.text('Pago pendiente'), findsOneWidget);
    expect(find.text('Jugada'), findsOneWidget);

    await tester.tap(find.text('Canchas El Torneo'));
    await tester.pumpAndSettle();

    expect(find.text('Detalle de la reserva'), findsOneWidget);
    expect(find.text('MD-000002'), findsOneWidget);
    expect(find.textContaining('18:00–19:00 (1 h)'), findsOneWidget);

    await tester.tap(find.textContaining('Completar pago (simulado)'));
    await tester.pumpAndSettle();

    expect(api.paidId, 2);
    expect(find.text('Confirmada'), findsOneWidget);
    expect(find.text('Total pagado'), findsOneWidget);
    expect(find.textContaining('Completar pago (simulado)'), findsNothing);

    await tester.pageBack();
    await tester.pumpAndSettle();

    expect(api.loads, 2);
    expect(find.text('Confirmada'), findsOneWidget);
  });

  testWidgets('courts booked together are one reservation with every range in its detail', (tester) async {
    final api = _FakeCourtApi([
      _reservation(id: 11, date: tomorrow, status: 'paid', bookingCode: 'BKBOYC82', field: 'Cancha 2', start: '17:00', end: '19:00'),
      _reservation(id: 12, date: tomorrow, status: 'paid', bookingCode: 'BKBOYC82', field: 'Cancha 1', start: '17:00', end: '19:00'),
    ]);

    await tester.pumpWidget(MaterialApp(home: MyReservationsScreen(courtApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Canchas El Torneo'), findsOneWidget);
    expect(find.text('Cancha 2 · 17:00–19:00'), findsOneWidget);
    expect(find.text('Cancha 1 · 17:00–19:00'), findsOneWidget);
    expect(find.text('Bs 160'), findsOneWidget);

    await tester.tap(find.text('Canchas El Torneo'));
    await tester.pumpAndSettle();

    expect(find.text('Canchas reservadas'), findsOneWidget);
    expect(find.text('Cancha 2 · Fútbol 5'), findsOneWidget);
    expect(find.text('Cancha 1 · Fútbol 5'), findsOneWidget);
    expect(find.text('BKBOYC82'), findsOneWidget);
    expect(find.text('Total pagado'), findsOneWidget);
    expect(find.text('Bs 160'), findsOneWidget);
  });

  testWidgets('a booking offers "Crear cancha" and, once created, "Ver cancha creada"', (tester) async {
    tester.view.physicalSize = const Size(1080, 3000);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final matchApi = MatchApiService(baseUrl: 'http://test');
    Future<void> openDetail(List<CourtReservationModel> reservations) async {
      await tester.pumpWidget(
        MaterialApp(
          key: UniqueKey(),
          home: MyReservationsScreen(courtApiService: _FakeCourtApi(reservations), matchApiService: matchApi, currentUserId: 1),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Canchas El Torneo'));
      await tester.pumpAndSettle();
    }

    await openDetail([_reservation(id: 4, date: tomorrow, status: 'paid', bookingCode: 'BK1')]);
    expect(find.text('¿Juegas con más gente?'), findsOneWidget);
    await tester.tap(find.text('Crear cancha'));
    await tester.pumpAndSettle();
    expect(find.byType(CreateMatchScreen), findsOneWidget);
    expect(tester.widget<CreateMatchScreen>(find.byType(CreateMatchScreen)).initialBookingCode, 'BK1');

    await openDetail([_reservation(id: 4, date: tomorrow, status: 'paid', bookingCode: 'BK1', matchId: 77)]);
    expect(find.text('Ya creaste la cancha de esta reserva'), findsOneWidget);
    expect(find.text('Crear cancha'), findsNothing);
    await tester.tap(find.text('Ver cancha creada'));
    await tester.pumpAndSettle();
    expect(tester.widget<MatchDetailScreen>(find.byType(MatchDetailScreen)).matchId, 77);
  });

  testWidgets('past or cancelled bookings do not offer creating a match', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MyReservationsScreen(
          courtApiService: _FakeCourtApi([_reservation(id: 4, date: lastWeek, status: 'paid', bookingCode: 'BK1')]),
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          currentUserId: 1,
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Canchas El Torneo'));
    await tester.pumpAndSettle();
    expect(find.text('Crear cancha'), findsNothing);
  });

  testWidgets('home entry counts courts booked together as one reservation', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MyReservationsSection(
            reservations: [
              _reservation(id: 11, date: tomorrow, status: 'paid', bookingCode: 'B1', field: 'Cancha 2'),
              _reservation(id: 12, date: tomorrow, status: 'paid', bookingCode: 'B1', field: 'Cancha 1'),
            ],
            onTap: () {},
          ),
        ),
      ),
    );

    expect(find.text('Mis reservas (1 próxima)'), findsOneWidget);
  });

  testWidgets('a reservation cancelled by the venue shows the reason and the refund', (tester) async {
    final api = _FakeCourtApi([
      _reservation(
        id: 3,
        date: tomorrow,
        status: 'cancelled',
        cancellationReason: 'Mantenimiento de la cancha',
        wasPaid: true,
      ),
    ]);

    await tester.pumpWidget(MaterialApp(home: MyReservationsScreen(courtApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Próximas'), findsNothing);
    expect(find.text('Anteriores'), findsOneWidget);
    expect(find.text('Anulada'), findsOneWidget);

    await tester.tap(find.text('Canchas El Torneo'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Motivo: Mantenimiento de la cancha'), findsOneWidget);
    expect(find.textContaining('te devolverá Bs 80'), findsOneWidget);
    expect(find.textContaining('Completar pago (simulado)'), findsNothing);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi(this.reservations) : super(baseUrl: 'http://test');

  List<CourtReservationModel> reservations;
  int? paidId;
  int loads = 0;

  @override
  Future<List<CourtReservationModel>> myReservations() async {
    loads++;
    return reservations;
  }

  @override
  Future<CourtReservationModel> pay(int reservationId) async {
    paidId = reservationId;
    final paid = _reservation(
      id: reservationId,
      date: DateTime.now().add(const Duration(days: 1)),
      status: 'paid',
    );
    reservations = [
      for (final item in reservations) item.id == reservationId ? paid : item,
    ];
    return paid;
  }
}
