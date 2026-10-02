/// One row of a leaderboard. Only the fields of its category are set.
class RankingEntry {
  const RankingEntry({
    required this.id,
    required this.name,
    this.subtitle,
    this.imageUrl,
    this.color,
    this.shortName,
    this.rating,
    this.reviewsCount = 0,
    this.bookings = 0,
    this.unitsSold = 0,
    this.price,
    this.orders = 0,
    this.matchesPlayed = 0,
    this.played = 0,
    this.won = 0,
    this.drawn = 0,
    this.lost = 0,
    this.goalDifference = 0,
    this.points = 0,
  });

  final int id;
  final String name;
  final String? subtitle;
  final String? imageUrl;

  /// Team colour "#RRGGBB" and abbreviation (teams only).
  final String? color;
  final String? shortName;

  final double? rating;
  final int reviewsCount;
  final int bookings;
  final int unitsSold;
  final double? price;
  final int orders;
  final int matchesPlayed;
  final int played;
  final int won;
  final int drawn;
  final int lost;
  final int goalDifference;
  final int points;

  factory RankingEntry.fromJson(Map<String, dynamic> json) {
    int count(String key) => (json[key] as num?)?.toInt() ?? 0;

    return RankingEntry(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      subtitle: json['subtitle'] as String?,
      imageUrl: json['image_url'] as String?,
      color: json['color'] as String?,
      shortName: json['short_name'] as String?,
      rating: (json['rating'] as num?)?.toDouble(),
      reviewsCount: count('reviews_count'),
      bookings: count('bookings'),
      unitsSold: count('units_sold'),
      price: (json['price'] as num?)?.toDouble(),
      orders: count('orders'),
      matchesPlayed: count('matches_played'),
      played: count('played'),
      won: count('won'),
      drawn: count('drawn'),
      lost: count('lost'),
      goalDifference: count('goal_difference'),
      points: count('points'),
    );
  }
}

enum RankingPeriod {
  all('all', 'Histórico'),
  month('month', 'Este mes');

  const RankingPeriod(this.value, this.label);

  final String value;
  final String label;
}

/// Leaderboards from `GET /api/rankings`.
class RankingModel {
  const RankingModel({
    required this.period,
    this.courts = const [],
    this.teams = const [],
    this.players = const [],
    this.products = const [],
    this.stores = const [],
  });

  final RankingPeriod period;
  final List<RankingEntry> courts;
  final List<RankingEntry> teams;
  final List<RankingEntry> players;
  final List<RankingEntry> products;
  final List<RankingEntry> stores;

  factory RankingModel.fromJson(Map<String, dynamic> json) {
    List<RankingEntry> list(String key) =>
        (json[key] as List<dynamic>?)
            ?.map((item) => RankingEntry.fromJson(item as Map<String, dynamic>))
            .toList() ??
        const [];

    return RankingModel(
      period: json['period'] == 'month'
          ? RankingPeriod.month
          : RankingPeriod.all,
      courts: list('courts'),
      teams: list('teams'),
      players: list('players'),
      products: list('products'),
      stores: list('stores'),
    );
  }
}
