import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/screens/home/widgets/my_reservations_section.dart';
import 'package:frontend/screens/my_reservations/my_reservations_screen.dart';
import 'package:frontend/services/court_api_service.dart';

String _day(DateTime date) =>
    '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

CourtReservationModel _reservation({
  required int id,
  required DateTime date,
  required String status,
  String venue = 'Canchas El Torneo',
}) {
  return CourtReservationModel(
    id: id,
    date: _day(date),
    startTime: '18:00',
    endTime: '19:00',
    hours: 1,
    amount: 80,
    status: status,
    sportName: 'Fútbol 5',
    fieldName: 'Cancha A',
    venueName: venue,
    paymentReference: 'MD-${id.toString().padLeft(6, '0')}',
    paymentExpiresAt: DateTime.now().add(const Duration(minutes: 10)),
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
    expect(find.text('18:00 – 19:00'), findsOneWidget);

    await tester.tap(find.text('Completar pago (simulado)'));
    await tester.pumpAndSettle();

    expect(api.paidId, 2);
    expect(find.text('Confirmada'), findsOneWidget);
    expect(find.text('Total pagado'), findsOneWidget);
    expect(find.text('Completar pago (simulado)'), findsNothing);

    await tester.pageBack();
    await tester.pumpAndSettle();

    expect(api.loads, 2);
    expect(find.text('Confirmada'), findsOneWidget);
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
