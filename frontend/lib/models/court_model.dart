import 'sport_model.dart';

/// A physical court inside a sports center (e.g. "Cancha 1"), with the sports it hosts.
class CourtFieldOption {
  const CourtFieldOption({
    required this.id,
    required this.name,
    this.sports = const [],
  });

  final int id;
  final String name;
  final List<SportModel> sports;

  /// "Cancha 1 (Pádel/Wally)".
  String get label => sports.isEmpty
      ? name
      : '$name (${sports.map((sport) => sport.name).join('/')})';

  factory CourtFieldOption.fromJson(Map<String, dynamic> json) {
    return CourtFieldOption(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      sports:
          (json['sports'] as List<dynamic>?)
              ?.map((item) => SportModel.fromJson(item as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  static List<CourtFieldOption> listFromJson(Object? value) {
    if (value is! List) return const [];
    return value
        .map((item) => CourtFieldOption.fromJson(item as Map<String, dynamic>))
        .toList();
  }
}

class CourtModel {
  const CourtModel({
    required this.id,
    required this.name,
    required this.address,
    this.ownerId,
    this.cityName,
    this.latitude,
    this.longitude,
    this.openingTime,
    this.closingTime,
    this.photos = const [],
    this.sports = const [],
    this.fields = const [],
  });

  final int id;
  final int? ownerId;
  final String name;
  final String address;

  /// Name of the city the center belongs to (`city.name` in the API).
  final String? cityName;
  final double? latitude;
  final double? longitude;
  final String? openingTime;
  final String? closingTime;
  final List<String> photos;
  final List<SportModel> sports;

  /// Physical courts of this sports center.
  final List<CourtFieldOption> fields;

  factory CourtModel.fromJson(Map<String, dynamic> json) {
    return CourtModel(
      id: (json['id'] as num).toInt(),
      ownerId: (json['owner_id'] as num?)?.toInt(),
      name: json['name'] as String,
      address: json['address'] as String,
      cityName: (json['city'] as Map<String, dynamic>?)?['name'] as String?,
      latitude: _toDouble(json['latitude']),
      longitude: _toDouble(json['longitude']),
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      photos: _parsePhotos(json['photos']),
      sports:
          (json['sports'] as List<dynamic>?)
              ?.map((item) => SportModel.fromJson(item as Map<String, dynamic>))
              .toList() ??
          const [],
      fields: CourtFieldOption.listFromJson(json['fields']),
    );
  }

  static double? _toDouble(Object? value) {
    if (value == null) return null;
    return double.tryParse(value.toString());
  }

  /// Handles both the `/api/courts` shape (list of URL strings) and the
  /// match-detail shape (list of `{url: ...}` objects).
  static List<String> _parsePhotos(Object? value) {
    if (value is! List) return const [];
    return value
        .map(
          (item) => item is String
              ? item
              : (item as Map<String, dynamic>)['url'] as String,
        )
        .toList();
  }
}
