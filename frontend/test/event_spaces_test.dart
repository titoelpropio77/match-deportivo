import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/event_space_model.dart';
import 'package:frontend/screens/event_spaces/event_space_detail_screen.dart';
import 'package:frontend/screens/event_spaces/event_spaces_screen.dart';
import 'package:frontend/screens/event_spaces/event_spaces_teaser.dart';
import 'package:frontend/screens/event_spaces/my_events_screen.dart';
import 'package:frontend/services/event_space_api_service.dart';

const venue = EventSpaceVenueModel(id: 10, name: 'Complejo Wally Sur', address: 'Santa Cruz');

const grill = EventSpaceModel(
  id: 1,
  name: 'Parrillero La Brasa',
  type: LabeledKey(key: 'grill', label: 'Parrillero'),
  pricePerHour: 80,
  capacity: 12,
  minHours: 2,
  description: 'Techado junto a las canchas.',
  amenities: [LabeledKey(key: 'grill', label: 'Parrilla'), LabeledKey(key: 'restrooms', label: 'Baños')],
  rules: 'Música hasta las 23:00.',
  openingTime: '18:00',
  closingTime: '23:00',
  venue: venue,
);

CourtSlotModel slot(String start, {bool available = true}) {
  final end = '${(int.parse(start.split(':')[0]) + 1).toString().padLeft(2, '0')}:00';
  return CourtSlotModel(start: start, end: end, available: available, status: available ? 'available' : 'reserved');
}

/// 18–23 with 20:00 taken.
final availability = EventSpaceAvailabilityModel(
  date: '2026-10-02',
  slots: [slot('18:00'), slot('19:00'), slot('20:00', available: false), slot('21:00'), slot('22:00')],
);

void main() {
  test('start times only where the whole stay fits in free hours', () {
    expect(availability.startsFor(1), ['18:00', '19:00', '21:00', '22:00']);
    expect(availability.startsFor(2), ['18:00', '21:00']);
    expect(availability.startsFor(3), isEmpty);
  });

  testWidgets('the space is presented and booked with the simulated QR', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final api = _FakeEventSpaceApi();

    await tester.pumpWidget(
      MaterialApp(home: EventSpaceDetailScreen(space: grill, eventSpaceApiService: api)),
    );
    await tester.pumpAndSettle();

    expect(find.text('PARRILLERO'), findsOneWidget);
    expect(find.text('personas máx.'), findsOneWidget);
    expect(find.text('Parrilla'), findsOneWidget);
    expect(find.text('Normas del espacio'), findsOneWidget);
    final page = find.byType(Scrollable).first;
    await tester.scrollUntilVisible(find.widgetWithText(ChoiceChip, '18:00'), 200, scrollable: page);
    expect(find.text('Mínimo 2 horas'), findsOneWidget);
    // Minimum stay is 2 hours: 19:00 does not fit before the 20:00 booking.
    expect(find.widgetWithText(ChoiceChip, '18:00'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, '19:00'), findsNothing);
    expect(find.text('Bs 160'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, '21:00'));
    await tester.pump();
    await tester.scrollUntilVisible(find.widgetWithText(ChoiceChip, 'Cumpleaños'), -200, scrollable: page);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Cumpleaños'));
    await tester.pump();
    expect(find.text('21:00–23:00 · 10 personas'), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Reservar'));
    await tester.pumpAndSettle();
    expect(find.text('Cumpleaños'), findsOneWidget);

    await tester.tap(find.textContaining('Pagar con QR'));
    await tester.pump();
    await tester.pump();
    expect(api.reserved, {'start': '21:00', 'hours': 2, 'guests': 10, 'event_type': 'birthday'});
    expect(find.text('Referencia: EABC1234'), findsOneWidget);

    await tester.ensureVisible(find.text('Simular pago'));
    await tester.pump();
    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();
    expect(find.text('¡Todo listo para tu evento!'), findsOneWidget);
  });

  testWidgets('the sports center teaser stays hidden without event spaces', (tester) async {
    final api = _FakeEventSpaceApi()..spaces = [];
    await tester.pumpWidget(
      MaterialApp(home: Scaffold(body: EventSpacesTeaser(courtId: 10, eventSpaceApiService: api))),
    );
    await tester.pumpAndSettle();
    expect(find.text('También para tus eventos'), findsNothing);

    api.spaces = [grill];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: EventSpacesTeaser(key: const ValueKey('with'), courtId: 10, eventSpaceApiService: api),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('También para tus eventos'), findsOneWidget);
    expect(find.text('Parrillero La Brasa'), findsOneWidget);
  });

  testWidgets('the list filters by group size', (tester) async {
    final api = _FakeEventSpaceApi();
    await tester.pumpWidget(MaterialApp(home: EventSpacesScreen(eventSpaceApiService: api)));
    await tester.pumpAndSettle();
    expect(find.text('Parrillero La Brasa'), findsOneWidget);
    expect(find.text('Hasta 12 personas'), findsOneWidget);

    await tester.tap(find.text('15+ personas'));
    await tester.pumpAndSettle();
    expect(api.lastGuests, 15);
    expect(find.text('No hay espacios para 15 personas o más.'), findsOneWidget);
  });

  testWidgets('my events shows status and lets pay a pending one', (tester) async {
    final api = _FakeEventSpaceApi();
    await tester.pumpWidget(MaterialApp(home: MyEventsScreen(eventSpaceApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Parrillero La Brasa'), findsOneWidget);
    expect(find.text('Pago pendiente'), findsOneWidget);
    expect(find.widgetWithText(TextButton, 'Pagar'), findsOneWidget);
  });
}

class _FakeEventSpaceApi extends EventSpaceApiService {
  _FakeEventSpaceApi() : super(baseUrl: 'http://test');

  List<EventSpaceModel> spaces = [grill];
  int? lastGuests;
  Map<String, Object?>? reserved;

  EventSpaceReservationModel _reservation(String status, {String start = '21:00', int hours = 2}) {
    final tomorrow = DateTime.now().add(const Duration(days: 1));
    return EventSpaceReservationModel(
      id: 7,
      code: 'EABC1234',
      date: DateTime(tomorrow.year, tomorrow.month, tomorrow.day),
      startTime: start,
      endTime: '${int.parse(start.split(':')[0]) + hours}:00',
      hours: hours,
      guests: 10,
      amount: 160,
      status: status,
      eventType: const LabeledKey(key: 'birthday', label: 'Cumpleaños'),
      paymentExpiresAt: DateTime.now().add(const Duration(minutes: 15)),
      space: grill,
    );
  }

  @override
  Future<List<EventSpaceModel>> list({int? courtId, int? guests}) async {
    lastGuests = guests;
    return spaces.where((space) => guests == null || space.capacity >= guests).toList();
  }

  @override
  Future<EventSpaceAvailabilityModel> availability({required int spaceId, required DateTime date}) async =>
      availability_;

  @override
  Future<EventSpaceReservationModel> reserve({
    required int spaceId,
    required DateTime date,
    required String startTime,
    required int hours,
    required int guests,
    String? eventType,
    String? notes,
  }) async {
    reserved = {'start': startTime, 'hours': hours, 'guests': guests, 'event_type': eventType};
    return _reservation('pending_payment', start: startTime, hours: hours);
  }

  @override
  Future<EventSpaceReservationModel> pay(int reservationId) async => _reservation('paid');

  @override
  Future<void> cancel(int reservationId) async {}

  @override
  Future<List<EventSpaceReservationModel>> myReservations() async => [_reservation('pending_payment')];
}

final availability_ = availability;
