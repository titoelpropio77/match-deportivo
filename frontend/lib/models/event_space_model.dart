import 'package:flutter/material.dart';

import 'court_field_model.dart';

/// `{key, label}` pair the API uses for enums (space type, amenities, event type).
class LabeledKey {
  const LabeledKey({required this.key, required this.label});

  final String key;
  final String label;

  factory LabeledKey.fromJson(Map<String, dynamic> json) {
    return LabeledKey(key: json['key'] as String, label: json['label'] as String);
  }
}

class EventSpaceVenueModel {
  const EventSpaceVenueModel({
    required this.id,
    required this.name,
    required this.address,
    this.cityName,
    this.photos = const [],
  });

  final int id;
  final String name;
  final String address;
  final String? cityName;
  final List<String> photos;

  factory EventSpaceVenueModel.fromJson(Map<String, dynamic> json) {
    final city = json['city'] as Map<String, dynamic>?;
    return EventSpaceVenueModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String? ?? '',
      cityName: city?['name'] as String?,
      photos: (json['photos'] as List<dynamic>?)?.map((item) => item as String).toList() ?? const [],
    );
  }
}

/// Space of a sports center rented by the hour for gatherings (grill area, quincho, hall...).
class EventSpaceModel {
  const EventSpaceModel({
    required this.id,
    required this.name,
    required this.type,
    required this.pricePerHour,
    required this.capacity,
    this.minHours = 1,
    this.description,
    this.amenities = const [],
    this.rules,
    this.photoUrl,
    this.openingTime,
    this.closingTime,
    this.venue,
  });

  final int id;
  final String name;
  final LabeledKey type;
  final double pricePerHour;

  /// Maximum number of guests.
  final int capacity;
  final int minHours;
  final String? description;
  final List<LabeledKey> amenities;
  final String? rules;
  final String? photoUrl;
  final String? openingTime;
  final String? closingTime;
  final EventSpaceVenueModel? venue;

  /// Own photo, or the sports center's first one.
  String? get coverUrl => photoUrl ?? venue?.photos.firstOrNull;

  factory EventSpaceModel.fromJson(Map<String, dynamic> json) {
    final venue = json['venue'] as Map<String, dynamic>?;
    return EventSpaceModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      type: LabeledKey.fromJson(json['type'] as Map<String, dynamic>),
      pricePerHour: (json['price_per_hour'] as num).toDouble(),
      capacity: (json['capacity'] as num).toInt(),
      minHours: (json['min_hours'] as num?)?.toInt() ?? 1,
      description: json['description'] as String?,
      amenities: (json['amenities'] as List<dynamic>? ?? const [])
          .map((item) => LabeledKey.fromJson(item as Map<String, dynamic>))
          .toList(),
      rules: json['rules'] as String?,
      photoUrl: json['photo_url'] as String?,
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      venue: venue == null ? null : EventSpaceVenueModel.fromJson(venue),
    );
  }
}

class EventSpaceAvailabilityModel {
  const EventSpaceAvailabilityModel({required this.date, required this.slots});

  final String date;
  final List<CourtSlotModel> slots;

  factory EventSpaceAvailabilityModel.fromJson(Map<String, dynamic> json) {
    return EventSpaceAvailabilityModel(
      date: json['date'] as String,
      slots: (json['slots'] as List<dynamic>)
          .map((item) => CourtSlotModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }

  /// Start times where [hours] consecutive free hours fit.
  List<String> startsFor(int hours) {
    final starts = <String>[];
    for (var index = 0; index + hours <= slots.length; index++) {
      if (slots.sublist(index, index + hours).every((slot) => slot.available)) {
        starts.add(slots[index].start);
      }
    }
    return starts;
  }
}

class EventSpaceReservationModel {
  const EventSpaceReservationModel({
    required this.id,
    required this.code,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.hours,
    required this.guests,
    required this.amount,
    required this.status,
    this.eventType,
    this.paymentExpiresAt,
    this.paidAt,
    this.cancelledByVenue = false,
    this.cancellationReason,
    this.refundedAt,
    this.notes,
    this.space,
  });

  final int id;
  final String code;
  final DateTime date;
  final String startTime;
  final String endTime;
  final int hours;
  final int guests;
  final double amount;

  /// `pending_payment`, `confirmed`, `paid` or `cancelled`.
  final String status;
  final LabeledKey? eventType;
  final DateTime? paymentExpiresAt;
  final DateTime? paidAt;
  final bool cancelledByVenue;
  final String? cancellationReason;
  final DateTime? refundedAt;
  final String? notes;
  final EventSpaceModel? space;

  bool get isPending => status == 'pending_payment';
  bool get isCancelled => status == 'cancelled';

  DateTime get endsAt {
    final parts = endTime.split(':');
    return DateTime(date.year, date.month, date.day, int.parse(parts[0]), int.parse(parts[1]));
  }

  bool get isUpcoming => !isCancelled && endsAt.isAfter(DateTime.now());

  factory EventSpaceReservationModel.fromJson(Map<String, dynamic> json) {
    final eventType = json['event_type'] as Map<String, dynamic>?;
    final space = json['space'] as Map<String, dynamic>?;
    DateTime? parse(String key) {
      final value = json[key] as String?;
      return value == null ? null : DateTime.parse(value).toLocal();
    }

    return EventSpaceReservationModel(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String,
      date: DateTime.parse(json['date'] as String),
      startTime: json['start_time'] as String,
      endTime: json['end_time'] as String,
      hours: (json['hours'] as num).toInt(),
      guests: (json['guests'] as num).toInt(),
      amount: (json['amount'] as num).toDouble(),
      status: json['status'] as String,
      eventType: eventType == null ? null : LabeledKey.fromJson(eventType),
      paymentExpiresAt: parse('payment_expires_at'),
      paidAt: parse('paid_at'),
      cancelledByVenue: json['cancelled_by_venue'] as bool? ?? false,
      cancellationReason: json['cancellation_reason'] as String?,
      refundedAt: parse('refunded_at'),
      notes: json['notes'] as String?,
      space: space == null ? null : EventSpaceModel.fromJson(space),
    );
  }
}

/// What the user can celebrate (keys of the backend's EventKind enum).
const eventKinds = [
  LabeledKey(key: 'barbecue', label: 'Asado'),
  LabeledKey(key: 'birthday', label: 'Cumpleaños'),
  LabeledKey(key: 'meeting', label: 'Reunión'),
  LabeledKey(key: 'celebration', label: 'Fiesta'),
  LabeledKey(key: 'corporate', label: 'Empresa'),
  LabeledKey(key: 'other', label: 'Otro'),
];

IconData eventSpaceTypeIcon(String key) => switch (key) {
      'grill' => Icons.outdoor_grill_outlined,
      'quincho' => Icons.cabin_outlined,
      'hall' => Icons.celebration_outlined,
      'terrace' => Icons.deck_outlined,
      'garden' => Icons.park_outlined,
      _ => Icons.celebration_outlined,
    };

IconData eventAmenityIcon(String key) => switch (key) {
      'grill' => Icons.outdoor_grill_outlined,
      'tables_chairs' => Icons.table_restaurant_outlined,
      'restrooms' => Icons.wc_outlined,
      'fridge' => Icons.kitchen_outlined,
      'kitchen' => Icons.soup_kitchen_outlined,
      'sound' => Icons.speaker_outlined,
      'air_conditioning' => Icons.ac_unit_outlined,
      'covered' => Icons.roofing_outlined,
      'lighting' => Icons.lightbulb_outline,
      'pool' => Icons.pool_outlined,
      'kids_area' => Icons.child_care_outlined,
      'parking' => Icons.local_parking_outlined,
      'wifi' => Icons.wifi,
      _ => Icons.check_circle_outline,
    };
