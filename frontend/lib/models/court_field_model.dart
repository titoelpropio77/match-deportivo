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
  });

  final String start;
  final String end;
  final bool available;

  factory CourtSlotModel.fromJson(Map<String, dynamic> json) {
    return CourtSlotModel(
      start: json['start'] as String,
      end: json['end'] as String,
      available: json['available'] as bool,
    );
  }
}

class CourtAvailabilityModel {
  const CourtAvailabilityModel({
    required this.date,
    required this.slots,
    required this.freeRanges,
  });

  final String date;
  final List<CourtSlotModel> slots;
  final List<({String start, String end})> freeRanges;

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
    );
  }
}
