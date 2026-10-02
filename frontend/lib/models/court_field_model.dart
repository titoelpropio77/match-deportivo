import 'rental_item_model.dart';
import 'sport_model.dart';

class CourtVenueModel {
  const CourtVenueModel({
    required this.id,
    required this.name,
    required this.address,
    this.openingTime,
    this.closingTime,
    this.photos = const [],
    this.eventSpacesCount,
  });

  final int id;
  final String name;
  final String address;
  final String? openingTime;
  final String? closingTime;
  final List<String> photos;

  /// Event spaces (grill areas, halls...) the center rents; only sent by the court list.
  final int? eventSpacesCount;

  factory CourtVenueModel.fromJson(Map<String, dynamic> json) {
    return CourtVenueModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String,
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      photos: (json['photos'] as List<dynamic>?)?.map((item) => item as String).toList() ??
          const [],
      eventSpacesCount: (json['event_spaces_count'] as num?)?.toInt(),
    );
  }
}

class CourtFieldModel {
  const CourtFieldModel({
    required this.id,
    required this.name,
    required this.pricePerHour,
    required this.venue,
    this.sports = const [],
  });

  final int id;
  final String name;
  final double pricePerHour;
  final CourtVenueModel venue;
  final List<SportModel> sports;

  factory CourtFieldModel.fromJson(Map<String, dynamic> json) {
    return CourtFieldModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      pricePerHour: (json['price_per_hour'] as num).toDouble(),
      venue: CourtVenueModel.fromJson(json['venue'] as Map<String, dynamic>),
      sports: (json['sports'] as List<dynamic>?)
              ?.map((item) => SportModel.fromJson(item as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }
}

class CourtSlotModel {
  const CourtSlotModel({
    required this.start,
    required this.end,
    required this.available,
    String? status,
    this.sportName,
  }) : status = status ?? (available ? 'available' : 'reserved');

  final String start;
  final String end;
  final bool available;

  /// `available`, `reserved` or `past` (the hour already started today).
  final String status;

  /// Sport the hour was booked for, only for reserved slots.
  final String? sportName;

  bool get isReserved => status == 'reserved';
  bool get isPast => status == 'past';

  factory CourtSlotModel.fromJson(Map<String, dynamic> json) {
    final sport = json['sport'] as Map<String, dynamic>?;
    return CourtSlotModel(
      start: json['start'] as String,
      end: json['end'] as String,
      available: json['available'] as bool,
      status: json['status'] as String?,
      sportName: sport?['name'] as String?,
    );
  }
}

class CourtAvailabilityModel {
  const CourtAvailabilityModel({
    required this.date,
    required this.slots,
    required this.freeRanges,
    this.venueFields = const [],
  });

  final String date;
  final List<CourtSlotModel> slots;
  final List<({String start, String end})> freeRanges;

  /// Every bookable court of the same sports center (without venue data).
  final List<CourtFieldSummaryModel> venueFields;

  factory CourtAvailabilityModel.fromJson(Map<String, dynamic> json) {
    final ranges = (json['free_ranges'] as List<dynamic>? ?? const [])
        .map((item) {
          final map = item as Map<String, dynamic>;
          return (start: map['start'] as String, end: map['end'] as String);
        })
        .toList();

    return CourtAvailabilityModel(
      date: json['date'] as String,
      slots: (json['slots'] as List<dynamic>)
          .map((item) => CourtSlotModel.fromJson(item as Map<String, dynamic>))
          .toList(),
      freeRanges: ranges,
      venueFields: (json['venue_fields'] as List<dynamic>? ?? const [])
          .map((item) => CourtFieldSummaryModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class CourtFieldSummaryModel {
  const CourtFieldSummaryModel({
    required this.id,
    required this.name,
    required this.pricePerHour,
    this.sports = const [],
  });

  final int id;
  final String name;
  final double pricePerHour;
  final List<SportModel> sports;

  factory CourtFieldSummaryModel.fromJson(Map<String, dynamic> json) {
    return CourtFieldSummaryModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      pricePerHour: (json['price_per_hour'] as num).toDouble(),
      sports: (json['sports'] as List<dynamic>?)
              ?.map((item) => SportModel.fromJson(item as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }
}

class CourtReservationModel {
  const CourtReservationModel({
    required this.id,
    this.bookingCode,
    this.matchId,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.hours,
    required this.amount,
    required this.status,
    this.sportName,
    this.fieldName,
    this.venueName,
    this.paymentReference,
    this.paymentExpiresAt,
    this.field,
    this.cancellationReason,
    this.cancelledByVenue = false,
    this.wasPaid = false,
    this.refunded = false,
    this.itemsAmount = 0,
    this.rentals = const [],
  });

  final int id;

  /// Shared by the reservations booked and paid together (several courts or hour ranges).
  final String? bookingCode;

  /// Match the user already created from this booking (only in "mis reservas").
  final int? matchId;
  final String date;
  final String startTime;
  final String endTime;
  final int hours;
  final double amount;
  final String status;
  final String? sportName;
  final String? fieldName;
  final String? venueName;
  final String? paymentReference;
  final DateTime? paymentExpiresAt;

  /// Court with its venue (address, photos), when the API includes it.
  final CourtFieldModel? field;

  /// Reason given by the sports center when it cancelled the booking.
  final String? cancellationReason;
  final bool cancelledByVenue;

  /// Payment was collected (it stays true after a cancellation).
  final bool wasPaid;
  final bool refunded;

  /// Part of [amount] that comes from rented gear.
  final double itemsAmount;
  final List<ReservedRentalModel> rentals;

  bool get isCancelled => status == 'cancelled';
  bool get isPaid => status == 'paid';
  bool get isPendingPayment => status == 'pending_payment';

  DateTime get startsAt => _at(startTime);
  DateTime get endsAt => _at(endTime);

  /// Still to be played (or being played right now); cancelled bookings never are.
  bool get isUpcoming => !isCancelled && endsAt.isAfter(DateTime.now());

  DateTime _at(String time) {
    final day = DateTime.parse(date);
    final parts = time.split(':');
    return DateTime(day.year, day.month, day.day, int.parse(parts[0]), int.parse(parts[1]));
  }

  factory CourtReservationModel.fromJson(Map<String, dynamic> json) {
    final sport = json['sport'] as Map<String, dynamic>?;
    final field = json['field'] as Map<String, dynamic>?;
    final venue = field?['venue'] as Map<String, dynamic>?;

    return CourtReservationModel(
      id: (json['id'] as num).toInt(),
      bookingCode: json['booking_code'] as String?,
      matchId: (json['match_id'] as num?)?.toInt(),
      date: json['date'] as String,
      startTime: json['start_time'] as String,
      endTime: json['end_time'] as String,
      hours: (json['hours'] as num).toInt(),
      amount: (json['amount'] as num).toDouble(),
      status: json['status'] as String,
      sportName: sport?['name'] as String?,
      fieldName: field?['name'] as String?,
      venueName: venue?['name'] as String?,
      paymentReference: json['payment_reference'] as String?,
      paymentExpiresAt: DateTime.tryParse(json['payment_expires_at'] as String? ?? '')?.toLocal(),
      field: venue == null ? null : CourtFieldModel.fromJson(field!),
      cancellationReason: json['cancellation_reason'] as String?,
      cancelledByVenue: json['cancelled_by_venue'] as bool? ?? false,
      wasPaid: json['paid_at'] != null,
      refunded: json['refunded_at'] != null,
      itemsAmount: (json['items_amount'] as num?)?.toDouble() ?? 0,
      rentals: (json['rentals'] as List<dynamic>? ?? const [])
          .map((item) => ReservedRentalModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

/// One hour range the player wants to book on one court, e.g. Cancha 1 09:00–11:00.
class BookingItem {
  const BookingItem({
    required this.field,
    required this.sport,
    required this.date,
    required this.startTime,
    required this.hours,
    this.rentals = const [],
  });

  final CourtFieldSummaryModel field;
  final SportModel sport;
  final DateTime date;
  final String startTime;
  final int hours;

  /// Gear rented with this range (balls, rackets...), charged with it.
  final List<RentalSelection> rentals;

  BookingItem withRentals(List<RentalSelection> rentals) => BookingItem(
        field: field,
        sport: sport,
        date: date,
        startTime: startTime,
        hours: hours,
        rentals: rentals,
      );

  String get endTime {
    final hour = int.parse(startTime.split(':')[0]) + hours;
    return '${hour.toString().padLeft(2, '0')}:${startTime.split(':')[1]}';
  }

  double get courtAmount => field.pricePerHour * hours;

  double get rentalsAmount =>
      rentals.fold(0, (total, rental) => total + rental.item.amountFor(rental.quantity, hours));

  /// Court hours plus rented gear.
  double get amount => courtAmount + rentalsAmount;

  Map<String, dynamic> toJson() => {
        'court_field_id': field.id,
        'sport_id': sport.id,
        'date': formatApiDate(date),
        'start_time': startTime,
        'hours': hours,
        if (rentals.isNotEmpty) 'rentals': rentals.map((rental) => rental.toJson()).toList(),
      };
}

/// A single hourly slot picked in the schedule.
class SelectedSlot {
  const SelectedSlot({
    required this.field,
    required this.sport,
    required this.date,
    required this.start,
  });

  final CourtFieldSummaryModel field;
  final SportModel sport;
  final DateTime date;
  final String start;

  String get key => slotKey(field.id, date, start);

  static String slotKey(int fieldId, DateTime date, String start) =>
      '$fieldId|${formatApiDate(date)}|$start';
}

/// Merges consecutive hours of the same court, day and sport into ranges:
/// 09–10 + 10–11 + 11–12 on Cancha 1 become one 09:00–12:00 (3 h) booking item.
List<BookingItem> groupSlotsIntoRanges(Iterable<SelectedSlot> slots) {
  int hourOf(String time) => int.parse(time.split(':')[0]);

  final sorted = slots.toList()
    ..sort((a, b) {
      final byDate = a.date.compareTo(b.date);
      if (byDate != 0) return byDate;
      final byField = a.field.name.compareTo(b.field.name);
      if (byField != 0) return byField;
      final byId = a.field.id.compareTo(b.field.id);
      if (byId != 0) return byId;
      return hourOf(a.start).compareTo(hourOf(b.start));
    });

  final items = <BookingItem>[];
  for (final slot in sorted) {
    final last = items.isEmpty ? null : items.last;
    final continues = last != null &&
        last.field.id == slot.field.id &&
        last.sport.id == slot.sport.id &&
        last.date == slot.date &&
        hourOf(last.endTime) == hourOf(slot.start);
    if (continues) {
      items[items.length - 1] = BookingItem(
        field: last.field,
        sport: last.sport,
        date: last.date,
        startTime: last.startTime,
        hours: last.hours + 1,
      );
    } else {
      items.add(BookingItem(
        field: slot.field,
        sport: slot.sport,
        date: slot.date,
        startTime: slot.start,
        hours: 1,
      ));
    }
  }
  return items;
}

/// Reservations booked together and paid with one QR.
class CourtBookingModel {
  const CourtBookingModel({
    required this.code,
    required this.amount,
    required this.hours,
    required this.status,
    required this.reservations,
    this.paymentExpiresAt,
  });

  final String code;
  final double amount;
  final int hours;
  final String status;
  final DateTime? paymentExpiresAt;
  final List<CourtReservationModel> reservations;

  bool get isPaid => status == 'paid';

  factory CourtBookingModel.fromJson(Map<String, dynamic> json) {
    return CourtBookingModel(
      code: json['code'] as String,
      amount: (json['amount'] as num).toDouble(),
      hours: (json['hours'] as num).toInt(),
      status: json['status'] as String,
      paymentExpiresAt: DateTime.tryParse(json['payment_expires_at'] as String? ?? '')?.toLocal(),
      reservations: (json['reservations'] as List<dynamic>)
          .map((item) => CourtReservationModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

String formatApiDate(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

/// What the player booked in one go: every court/hour range sharing a booking code
/// (or a single reservation booked before multi-court booking existed).
class ReservationGroup {
  ReservationGroup(this.reservations)
      : assert(reservations.isNotEmpty),
        code = reservations.first.bookingCode;

  final String? code;

  /// Sorted by date and start time.
  final List<CourtReservationModel> reservations;

  CourtReservationModel get first => reservations.first;

  /// Match created from this booking, if any.
  int? get matchId => reservations.map((item) => item.matchId).whereType<int>().firstOrNull;

  String? get venueName => first.venueName;
  CourtFieldModel? get field => first.field;
  String get reference => first.paymentReference ?? code ?? 'MD-${first.id}';

  List<CourtReservationModel> get active => reservations.where((item) => !item.isCancelled).toList();

  /// Cancelled only when every range was cancelled.
  bool get isCancelled => active.isEmpty;
  bool get isPaid => !isCancelled && active.every((item) => item.isPaid);
  bool get isPendingPayment => active.any((item) => item.isPendingPayment);
  bool get isUpcoming => active.any((item) => item.isUpcoming);

  /// Some ranges were cancelled by the venue while others stay.
  bool get isPartiallyCancelled => !isCancelled && active.length < reservations.length;

  DateTime get startsAt => first.startsAt;
  DateTime? get paymentExpiresAt => first.paymentExpiresAt;

  double get amount => (isCancelled ? reservations : active).fold(0, (total, item) => total + item.amount);
  int get hours => (isCancelled ? reservations : active).fold(0, (total, item) => total + item.hours);

  /// Groups by booking code keeping the API order (upcoming first), placed where its first range appears.
  static List<ReservationGroup> fromReservations(List<CourtReservationModel> reservations) {
    final byKey = <String, List<CourtReservationModel>>{};
    for (final reservation in reservations) {
      byKey.putIfAbsent(reservation.bookingCode ?? '#${reservation.id}', () => []).add(reservation);
    }
    return byKey.values.map((items) {
      items.sort((a, b) => a.startsAt.compareTo(b.startsAt));
      return ReservationGroup(items);
    }).toList();
  }
}
