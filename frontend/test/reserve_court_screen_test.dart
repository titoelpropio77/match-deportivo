import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/reserve_court/reserve_courts_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';

const wally = SportModel(id: 1, key: 'wallyball', name: 'Wally');
const fronton = SportModel(id: 2, key: 'fronton', name: 'Frontón');

const cancha1 = CourtFieldSummaryModel(id: 1, name: 'Cancha 1', pricePerHour: 60, sports: [wally, fronton]);
const cancha2 = CourtFieldSummaryModel(id: 2, name: 'Cancha 2', pricePerHour: 80, sports: [wally]);

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

  test('consecutive hours of the same court become one range', () {
    final day = DateTime(2026, 10, 2);
    SelectedSlot slot(CourtFieldSummaryModel field, String start) =>
        SelectedSlot(field: field, sport: wally, date: day, start: start);

    final items = groupSlotsIntoRanges([
      slot(cancha1, '11:00'),
      slot(cancha1, '09:00'),
      slot(cancha2, '09:00'),
      slot(cancha1, '10:00'),
      slot(cancha1, '18:00'),
    ]);

    expect(
      items.map((item) => '${item.field.name} ${item.startTime}-${item.endTime} ${item.hours}h'),
      ['Cancha 1 09:00-12:00 3h', 'Cancha 1 18:00-19:00 1h', 'Cancha 2 09:00-10:00 1h'],
    );
    expect(items.fold<double>(0, (total, item) => total + item.amount), 60 * 4 + 80);
  });

  testWidgets('lists one card per sports center with all its sports', (tester) async {
    const venue = CourtVenueModel(id: 10, name: 'Complejo Wally', address: 'Santa Cruz', photos: []);
    final api = _FakeCourtApi(field)
      ..fields = [
        field,
        const CourtFieldModel(id: 2, name: 'Cancha 2', pricePerHour: 80, venue: venue, sports: [wally]),
        CourtFieldModel(
          id: 3,
          name: 'Cancha A',
          pricePerHour: 50,
          venue: const CourtVenueModel(id: 20, name: 'Arena Norte', address: 'Zona Norte', photos: []),
          sports: const [SportModel(id: 3, key: 'futbol_5', name: 'Fútbol 5')],
        ),
      ];

    await tester.pumpWidget(
      MaterialApp(home: ReserveCourtsScreen(courtApiService: api, sportApiService: _FakeSportApi())),
    );
    await tester.pumpAndSettle();

    expect(find.text('Complejo Wally'), findsOneWidget);
    expect(find.text('Arena Norte'), findsOneWidget);
    expect(find.text('Cancha 1'), findsNothing);
    expect(find.text('2 canchas disponibles'), findsOneWidget);
    expect(find.text('1 cancha disponible'), findsOneWidget);
    expect(find.text('Desde Bs 60 por hora'), findsOneWidget);
    expect(find.text('Bs 50 por hora'), findsOneWidget);
    expect(find.widgetWithText(Chip, 'Wally'), findsOneWidget);
    expect(find.widgetWithText(Chip, 'Frontón'), findsOneWidget);
    expect(find.widgetWithText(Chip, 'Fútbol 5'), findsOneWidget);
  });

  testWidgets('opened from another screen, paying returns there with true instead of going home', (tester) async {
    tester.view.physicalSize = const Size(1080, 3200);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final api = _FakeCourtApi(field);
    bool? result;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) => Column(
              children: [
                const Text('Pantalla de origen'),
                FilledButton(
                  onPressed: () async {
                    result = await Navigator.of(context).push<bool>(
                      MaterialPageRoute(
                        settings: const RouteSettings(name: ReserveCourtsScreen.routeName),
                        builder: (_) => ReserveCourtsScreen(
                          courtApiService: api,
                          sportApiService: _FakeSportApi(),
                          returnAfterBooking: true,
                        ),
                      ),
                    );
                  },
                  child: const Text('Abrir reservas'),
                ),
              ],
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Abrir reservas'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Complejo Wally'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('20:00–21:00'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuar · 1 hora · Bs 60'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pagar con QR · Bs 60'));
    await tester.pump();
    await tester.ensureVisible(find.text('Simular pago'));
    await tester.pump();
    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Continuar'));
    await tester.tap(find.text('Continuar'));
    await tester.pumpAndSettle();

    expect(result, isTrue);
    expect(find.text('Pantalla de origen'), findsOneWidget);
    expect(find.text('Reservar cancha'), findsNothing);
  });

  testWidgets('books several hours on several courts and pays them with one QR', (tester) async {
    tester.view.physicalSize = const Size(1080, 3200);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final api = _FakeCourtApi(field);
    await tester.pumpWidget(
      MaterialApp(
        home: ReserveCourtsScreen(courtApiService: api, sportApiService: _FakeSportApi()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Complejo Wally'));
    await tester.pumpAndSettle();

    expect(find.text('Reservado: 19:00–20:00 (Frontón)'), findsOneWidget);
    expect(find.text('Selecciona uno o más horarios'), findsOneWidget);

    await tester.tap(find.text('19:00–20:00'));
    await tester.pump();
    expect(find.text('Ese horario ya está reservado para Frontón.'), findsOneWidget);

    // Two consecutive hours on Cancha 1 → one 20:00–22:00 range.
    await tester.tap(find.text('20:00–21:00'));
    await tester.tap(find.text('21:00–22:00'));
    await tester.pumpAndSettle();

    expect(find.text('Cancha 1 · 20:00–22:00'), findsOneWidget);
    expect(find.text('Continuar · 2 horas · Bs 120'), findsOneWidget);

    // Tapping again unselects.
    await tester.tap(find.text('21:00–22:00'));
    await tester.pumpAndSettle();
    expect(find.text('Continuar · 1 hora · Bs 60'), findsOneWidget);
    await tester.tap(find.text('21:00–22:00'));
    await tester.pumpAndSettle();

    // Add an hour on another court: the selection is kept.
    await tester.tap(find.text('Cancha 2 · Bs 80/h'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('18:00–19:00'));
    await tester.pumpAndSettle();

    expect(find.text('Cancha 1 · Bs 60/h · 2 h'), findsOneWidget);
    expect(find.text('Cancha 2 · 18:00–19:00'), findsOneWidget);
    expect(find.text('Continuar · 3 horas · Bs 200'), findsOneWidget);

    await tester.tap(find.text('Continuar · 3 horas · Bs 200'));
    await tester.pumpAndSettle();

    expect(find.text('Detalle de la reserva'), findsOneWidget);
    expect(find.text('Cancha 1 · Wally'), findsOneWidget);
    expect(find.text('Cancha 2 · Wally'), findsOneWidget);

    await tester.tap(find.text('Pagar con QR · Bs 200'));
    await tester.pump();

    expect(api.bookedItems.map((item) => '${item.field.id} ${item.startTime} ${item.hours}'), ['1 20:00 2', '2 18:00 1']);
    expect(find.text('Referencia: BTEST123'), findsOneWidget);
    expect(find.text('Tus horarios quedan apartados por 14:59'), findsOneWidget);

    await tester.ensureVisible(find.text('Simular pago'));
    await tester.pump();
    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();

    expect(api.paidCode, 'BTEST123');
    expect(find.text('¡Canchas reservadas!'), findsOneWidget);
    expect(find.text('Total pagado'), findsOneWidget);

    // Back to the first screen (here the courts list), not to a blank page.
    await tester.ensureVisible(find.text('Volver al inicio'));
    await tester.tap(find.text('Volver al inicio'));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Reservar cancha'), findsOneWidget);
    expect(find.text('Complejo Wally'), findsOneWidget);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi(this.field) : super(baseUrl: 'http://test');

  final CourtFieldModel field;
  late List<CourtFieldModel> fields = [field];
  List<BookingItem> bookedItems = const [];
  String? paidCode;

  @override
  Future<List<CourtFieldModel>> listFields({int? sportId, DateTime? date}) async => fields;

  @override
  Future<CourtAvailabilityModel> availability({
    required int fieldId,
    required DateTime date,
  }) async {
    return const CourtAvailabilityModel(
      date: '2026-09-25',
      slots: [
        CourtSlotModel(start: '18:00', end: '19:00', available: true),
        CourtSlotModel(start: '19:00', end: '20:00', available: false, status: 'reserved', sportName: 'Frontón'),
        CourtSlotModel(start: '20:00', end: '21:00', available: true),
        CourtSlotModel(start: '21:00', end: '22:00', available: true),
      ],
      freeRanges: [
        (start: '18:00', end: '19:00'),
        (start: '20:00', end: '22:00'),
      ],
      venueFields: [cancha1, cancha2],
    );
  }

  CourtBookingModel _booking(String status) => CourtBookingModel(
        code: 'BTEST123',
        amount: 200,
        hours: 3,
        status: status,
        paymentExpiresAt: DateTime.now().add(const Duration(minutes: 15)),
        reservations: const [],
      );

  @override
  Future<CourtBookingModel> createBooking(List<BookingItem> items) async {
    bookedItems = items;
    return _booking('pending_payment');
  }

  @override
  Future<CourtBookingModel> payBooking(String code) async {
    paidCode = code;
    return _booking('paid');
  }
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [wally, fronton];
}
