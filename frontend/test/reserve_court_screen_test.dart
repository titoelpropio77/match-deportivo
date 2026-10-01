import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/reserve_court/reserve_courts_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';

const wally = SportModel(id: 1, key: 'wallyball', name: 'Wally');
const fronton = SportModel(id: 2, key: 'fronton', name: 'Frontón');

void main() {
  final field = CourtFieldModel(
    id: 1,
    name: 'Cancha 1',
    pricePerHour: 60,
    venue: const CourtVenueModel(
      id: 10,
      name: 'Complejo Wally',
      address: 'Santa Cruz',
      photos: [],
    ),
    sports: const [wally, fronton],
  );

  testWidgets('reserved hours show their sport and a simulated QR payment confirms the booking', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final api = _FakeCourtApi(field);
    await tester.pumpWidget(
      MaterialApp(
        home: ReserveCourtsScreen(
          courtApiService: api,
          sportApiService: _FakeSportApi(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Complejo Wally'), findsOneWidget);
    expect(find.text('Bs 60 por hora'), findsOneWidget);

    await tester.tap(find.text('Complejo Wally'));
    await tester.pumpAndSettle();

    expect(find.text('Libre: 18:00–19:00, 20:00–22:00'), findsOneWidget);
    expect(find.text('Reservado: 19:00–20:00 (Frontón)'), findsOneWidget);
    expect(find.text('Cancha 2 · Bs 80/h'), findsOneWidget);

    await tester.ensureVisible(find.text('19:00–20:00'));
    await tester.tap(find.text('19:00–20:00'));
    await tester.pump();
    expect(find.text('Ese horario ya está reservado para Frontón.'), findsOneWidget);

    await tester.tap(find.text('18:00–19:00'));
    await tester.pumpAndSettle();

    expect(find.text('Reservar · Bs 60'), findsOneWidget);

    await tester.ensureVisible(find.text('2 horas'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('2 horas'));
    await tester.pumpAndSettle();

    expect(find.text('No hay espacio para esas horas.'), findsOneWidget);

    await tester.ensureVisible(find.text('20:00–21:00'));
    await tester.tap(find.text('20:00–21:00'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('2 horas'));
    await tester.tap(find.text('2 horas'));
    await tester.pumpAndSettle();

    expect(find.text('Reservar · Bs 120'), findsOneWidget);

    await tester.tap(find.text('Reservar · Bs 120'));
    await tester.pumpAndSettle();

    expect(find.text('Detalle de la reserva'), findsOneWidget);
    expect(find.text('Wally'), findsWidgets);
    expect(find.text('20:00 – 22:00'), findsOneWidget);

    await tester.tap(find.text('Pagar con QR · Bs 120'));
    await tester.pump();

    expect(find.text('Referencia: MD-000007'), findsOneWidget);
    expect(find.text('El horario queda apartado por 14:59'), findsOneWidget);

    await tester.ensureVisible(find.text('Simular pago'));
    await tester.pump();
    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();

    expect(find.text('¡Cancha reservada!'), findsOneWidget);
    expect(find.text('Total pagado'), findsOneWidget);
    expect(api.paidId, 7);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi(this.field) : super(baseUrl: 'http://test');

  final CourtFieldModel field;
  int? paidId;

  @override
  Future<List<CourtFieldModel>> listFields({int? sportId, DateTime? date}) async => [field];

  @override
  Future<CourtAvailabilityModel> availability({
    required int fieldId,
    required DateTime date,
  }) async {
    return const CourtAvailabilityModel(
      date: '2026-09-25',
      slots: [
        CourtSlotModel(start: '18:00', end: '19:00', available: true),
        CourtSlotModel(
          start: '19:00',
          end: '20:00',
          available: false,
          status: 'reserved',
          sportName: 'Frontón',
        ),
        CourtSlotModel(start: '20:00', end: '21:00', available: true),
        CourtSlotModel(start: '21:00', end: '22:00', available: true),
      ],
      freeRanges: [
        (start: '18:00', end: '19:00'),
        (start: '20:00', end: '22:00'),
      ],
      venueFields: [
        CourtFieldSummaryModel(id: 1, name: 'Cancha 1', pricePerHour: 60, sports: [wally, fronton]),
        CourtFieldSummaryModel(id: 2, name: 'Cancha 2', pricePerHour: 80, sports: [wally]),
      ],
    );
  }

  CourtReservationModel _reservation(String status) => CourtReservationModel(
        id: 7,
        date: '2026-09-25',
        startTime: '20:00',
        endTime: '22:00',
        hours: 2,
        amount: 120,
        status: status,
        paymentReference: 'MD-000007',
        paymentExpiresAt: DateTime.now().add(const Duration(minutes: 15)),
      );

  @override
  Future<CourtReservationModel> reserve({
    required int fieldId,
    required int sportId,
    required DateTime date,
    required String startTime,
    required int hours,
  }) async =>
      _reservation('pending_payment');

  @override
  Future<CourtReservationModel> pay(int reservationId) async {
    paidId = reservationId;
    return _reservation('paid');
  }
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'wallyball', name: 'Wally'),
        SportModel(id: 2, key: 'fronton', name: 'Frontón'),
      ];
}
