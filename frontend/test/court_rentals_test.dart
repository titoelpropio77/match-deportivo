import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/rental_item_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/reserve_court/court_payment_screen.dart';
import 'package:frontend/services/court_api_service.dart';

const wally = SportModel(id: 1, key: 'wallyball', name: 'Wally');
const padel = SportModel(id: 2, key: 'padel', name: 'Pádel');
const cancha1 = CourtFieldSummaryModel(id: 1, name: 'Cancha 1', pricePerHour: 60, sports: [wally, padel]);
const venue = CourtVenueModel(id: 10, name: 'Complejo Wally Sur', address: 'Santa Cruz');

const ball = RentalItemModel(id: 5, sportId: 1, name: 'Pelota de wally', price: 10, priceType: 'flat', stock: 1);
const racket = RentalItemModel(id: 6, sportId: 2, name: 'Raqueta de pádel', price: 15, priceType: 'per_hour');

void main() {
  final day = DateTime(2026, 10, 2);
  final wallyRange = BookingItem(field: cancha1, sport: wally, date: day, startTime: '09:00', hours: 2);
  final padelRange = BookingItem(field: cancha1, sport: padel, date: day, startTime: '18:00', hours: 2);

  test('rented gear adds to the range amount and is sent with it', () {
    final item = padelRange.withRentals(const [RentalSelection(item: racket, quantity: 2)]);
    expect(item.courtAmount, 120);
    expect(item.rentalsAmount, 15 * 2 * 2);
    expect(item.amount, 180);
    expect(item.toJson()['rentals'], [
      {'rental_item_id': 6, 'quantity': 2},
    ]);
    expect(wallyRange.toJson().containsKey('rentals'), isFalse);
    expect(ball.amountFor(3, 2), 30);
  });

  testWidgets('gear of each range sport is offered and charged with the booking', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final api = _FakeCourtApi();

    await tester.pumpWidget(
      MaterialApp(
        home: CourtPaymentScreen(venue: venue, items: [wallyRange, padelRange], courtApiService: api),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('¿Necesitas algo más?'), findsOneWidget);
    expect(find.text('Pelota de wally'), findsOneWidget);
    expect(find.text('Raqueta de pádel'), findsOneWidget);
    final page = find.byType(Scrollable).first;
    await tester.scrollUntilVisible(find.text('Pagar con QR · Bs 240'), 200, scrollable: page);
    await tester.scrollUntilVisible(find.byTooltip('Agregar Pelota de wally'), -200, scrollable: page);

    await tester.tap(find.byTooltip('Agregar Raqueta de pádel'));
    await tester.pump();
    await tester.tap(find.byTooltip('Agregar Raqueta de pádel'));
    await tester.tap(find.byTooltip('Agregar Pelota de wally'));
    await tester.pump();
    // Only 1 ball in stock: no more can be added.
    expect(
      tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.add_circle_outline).first).onPressed,
      isNull,
    );
    await tester.scrollUntilVisible(find.text('2 × Raqueta de pádel'), -200, scrollable: page);
    expect(find.text('1 × Pelota de wally'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Pagar con QR · Bs 310'), 200, scrollable: page);
    await tester.ensureVisible(find.text('Pagar con QR · Bs 310'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Pagar con QR · Bs 310'));
    await tester.pump();
    await tester.pump();
    expect(api.booked.map((item) => item.toJson()['rentals']), [
      [
        {'rental_item_id': 5, 'quantity': 1},
      ],
      [
        {'rental_item_id': 6, 'quantity': 2},
      ],
    ]);
    expect(find.text('¿Necesitas algo más?'), findsNothing);
  });

  testWidgets('nothing is offered when the center rents no gear for those sports', (tester) async {
    final api = _FakeCourtApi()..items = const [racket];
    await tester.pumpWidget(
      MaterialApp(home: CourtPaymentScreen(venue: venue, items: [wallyRange], courtApiService: api)),
    );
    await tester.pumpAndSettle();
    expect(find.text('¿Necesitas algo más?'), findsNothing);
  });
}

class _FakeCourtApi extends CourtApiService {
  _FakeCourtApi() : super(baseUrl: 'http://test');

  List<RentalItemModel> items = const [ball, racket];
  List<BookingItem> booked = const [];

  @override
  Future<List<RentalItemModel>> rentalItems(int courtId) async => items;

  @override
  Future<CourtBookingModel> createBooking(List<BookingItem> items) async {
    booked = items;
    return CourtBookingModel(
      code: 'BTEST123',
      amount: items.fold(0, (total, item) => total + item.amount),
      hours: 4,
      status: 'pending_payment',
      paymentExpiresAt: DateTime.now().add(const Duration(minutes: 15)),
      reservations: const [],
    );
  }
}
