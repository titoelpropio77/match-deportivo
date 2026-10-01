import 'sport_model.dart';

class CourtVenueModel {
  const CourtVenueModel({
    required this.id,
    required this.name,
    required this.address,
    this.openingTime,
    this.closingTime,
    this.photos = const [],
  });

  final int id;
  final String name;
  final String address;
  final String? openingTime;
  final String? closingTime;
  final List<String> photos;

  factory CourtVenueModel.fromJson(Map<String, dynamic> json) {
    return CourtVenueModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String,
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      photos: (json['photos'] as List<dynamic>?)?.map((item) => item as String).toList() ??
          const [],
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
  });

  final int id;
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

  bool get isPaid => status == 'paid';
  bool get isPendingPayment => status == 'pending_payment';

  DateTime get startsAt => _at(startTime);
  DateTime get endsAt => _at(endTime);

  /// Still to be played (or being played right now).
  bool get isUpcoming => endsAt.isAfter(DateTime.now());

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
    );
  }
}
