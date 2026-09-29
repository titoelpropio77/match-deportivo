import 'sport_model.dart';

class CourtModel {
  const CourtModel({
    required this.id,
    required this.name,
    required this.address,
    this.ownerId,
    this.latitude,
    this.longitude,
    this.openingTime,
    this.closingTime,
    this.photos = const [],
    this.sports = const [],
  });

  final int id;
  final int? ownerId;
  final String name;
  final String address;
  final double? latitude;
  final double? longitude;
  final String? openingTime;
  final String? closingTime;
  final List<String> photos;
  final List<SportModel> sports;

  factory CourtModel.fromJson(Map<String, dynamic> json) {
    return CourtModel(
      id: (json['id'] as num).toInt(),
      ownerId: (json['owner_id'] as num?)?.toInt(),
      name: json['name'] as String,
      address: json['address'] as String,
      latitude: _toDouble(json['latitude']),
      longitude: _toDouble(json['longitude']),
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      photos: _parsePhotos(json['photos']),
      sports: (json['sports'] as List<dynamic>?)
              ?.map((item) => SportModel.fromJson(item as Map<String, dynamic>))
              .toList() ??
          const [],
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
        .map((item) => item is String ? item : (item as Map<String, dynamic>)['url'] as String)
        .toList();
  }
}
