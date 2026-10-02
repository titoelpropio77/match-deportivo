import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/court_field_model.dart';
import 'package:frontend/models/sport_model.dart';

const futbol = SportModel(id: 1, key: 'futbol_5', name: 'Fútbol 5');
const techada = CourtFieldSummaryModel(
  id: 1,
  name: 'Cancha techada',
  pricePerHour: 50,
  sports: [futbol],
  airConditioningPrice: 15,
  lightingPrice: 10,
  lightingFrom: '18:30',
);
const sencilla = CourtFieldSummaryModel(id: 2, name: 'Cancha 2', pricePerHour: 50, sports: [futbol]);

void main() {
  final day = DateTime(2026, 10, 2);

  test('night hours add the lighting price automatically', () {
    // 17–18 is before 18:30; 18–19 and 19–20 are lit.
    final item = BookingItem(field: techada, sport: futbol, date: day, startTime: '17:00', hours: 3);
    expect(item.litHours, 2);
    expect(item.lightingAmount, 20);
    expect(item.amount, 50 * 3 + 20);
    expect(item.toJson().containsKey('air_conditioning'), isFalse);

    final morning = BookingItem(field: techada, sport: futbol, date: day, startTime: '09:00', hours: 2);
    expect(morning.lightingAmount, 0);
  });

  test('air conditioning is charged per hour only when picked and offered', () {
    final item = BookingItem(field: techada, sport: futbol, date: day, startTime: '09:00', hours: 2)
        .withAirConditioning(true);
    expect(item.airConditioningAmount, 30);
    expect(item.amount, 100 + 30);
    expect(item.toJson()['air_conditioning'], isTrue);
    expect(item.withRentals(const []).airConditioning, isTrue);

    final withoutAc = BookingItem(field: sencilla, sport: futbol, date: day, startTime: '19:00', hours: 1)
        .withAirConditioning(true);
    expect(withoutAc.airConditioning, isFalse);
    expect(withoutAc.amount, 50);
  });

  test('court extras and reserved amounts are parsed from the API', () {
    final field = CourtFieldSummaryModel.fromJson({
      'id': 1,
      'name': 'Cancha 1',
      'price_per_hour': 50,
      'air_conditioning_price': 15,
      'lighting_price': 10,
      'lighting_from': '18:00',
    });
    expect(field.offersAirConditioning, isTrue);
    expect(field.isLitHour('17:00'), isFalse);
    expect(field.isLitHour('18:00'), isTrue);

    final slot = CourtSlotModel.fromJson({'start': '19:00', 'end': '20:00', 'available': true, 'lighting': true});
    expect(slot.lighting, isTrue);
  });
}
