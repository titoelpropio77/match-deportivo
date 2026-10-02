/// Why a sports center is highlighted on the home screen.
enum FeaturedHighlight {
  topRated('top_rated', 'Mejor calificado'),
  isNew('new', 'Nuevo');

  const FeaturedHighlight(this.value, this.label);

  final String value;
  final String label;

  static FeaturedHighlight? fromValue(Object? value) {
    for (final highlight in values) {
      if (highlight.value == value) return highlight;
    }
    return null;
  }
}

/// A sports center from `GET /api/courts/featured`.
class FeaturedCourtModel {
  const FeaturedCourtModel({
    required this.id,
    required this.name,
    required this.address,
    this.city,
    this.photoUrl,
    this.rating,
    this.reviewsCount = 0,
    this.minPrice,
    this.sports = const [],
    this.isNew = false,
    this.highlight,
  });

  final int id;
  final String name;
  final String address;
  final String? city;
  final String? photoUrl;

  /// Average 1-5, null while the center has no reviews.
  final double? rating;
  final int reviewsCount;

  /// Cheapest hourly price among its courts, null if it has none yet.
  final double? minPrice;
  final List<String> sports;
  final bool isNew;
  final FeaturedHighlight? highlight;

  factory FeaturedCourtModel.fromJson(Map<String, dynamic> json) {
    return FeaturedCourtModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String? ?? '',
      city: json['city'] as String?,
      photoUrl: json['photo_url'] as String?,
      rating: (json['rating'] as num?)?.toDouble(),
      reviewsCount: (json['reviews_count'] as num?)?.toInt() ?? 0,
      minPrice: (json['min_price'] as num?)?.toDouble(),
      sports: (json['sports'] as List<dynamic>?)?.map((item) => item as String).toList() ??
          const [],
      isNew: json['is_new'] as bool? ?? false,
      highlight: FeaturedHighlight.fromValue(json['highlight']),
    );
  }
}
