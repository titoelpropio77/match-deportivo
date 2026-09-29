import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/reserve_court/reserve_courts_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';

void main() {
  const wally = SportModel(id: 1, key: 'wallyball', name: 'Wally');
  const fronton = SportModel(id: 2, key: 'fronton', name: 'Frontón');

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

  testWidgets('occupied hour cannot be selected and pay shows the hourly total', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: ReserveCourtsScreen(
          courtApiService: _FakeCourtApi(field),
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
    expect(find.textContaining('Ocupado'), findsOneWidget);

    await tester.tap(find.text('18:00–19:00'));
    await tester.pumpAndSettle();

    expect(find.text('Pagar Bs 60'), findsOneWidget);

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

    expect(find.text('Pagar Bs 120'), findsOneWidget);

    await tester.tap(find.text('Pagar Bs 120'));
    await tester.pumpAndSettle();

    expect(find.text('Detalle de pago'), findsOneWidget);
    expect(find.text('Pago por QR · Bs 120'), findsOneWidget);
    expect(find.text('Wally'), findsWidgets);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi(this.field) : super(baseUrl: 'http://test');

  final CourtFieldModel field;

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
        CourtSlotModel(start: '19:00', end: '20:00', available: false),
        CourtSlotModel(start: '20:00', end: '21:00', available: true),
        CourtSlotModel(start: '21:00', end: '22:00', available: true),
      ],
      freeRanges: [
        (start: '18:00', end: '19:00'),
        (start: '20:00', end: '22:00'),
      ],
    );
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
