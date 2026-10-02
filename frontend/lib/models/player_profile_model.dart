import 'user_model.dart';

/// Public profile of a player: who they are, what they played and how others rated them.
class PlayerProfileModel {
  const PlayerProfileModel({
    required this.user,
    required this.isMe,
    required this.stats,
    required this.rating,
    this.age,
    this.topTags = const [],
    this.teams = const [],
    this.recentMatches = const [],
  });

  final UserModel user;
  final bool isMe;
  final int? age;
  final PlayerStats stats;
  final PlayerRatingSummary rating;
  final List<({String label, bool positive, int count})> topTags;
  final List<PlayerTeam> teams;
  final List<({int id, DateTime startTime, String? sport, String? court})> recentMatches;

  factory PlayerProfileModel.fromJson(Map<String, dynamic> json) {
    return PlayerProfileModel(
      user: UserModel.fromJson(json['user'] as Map<String, dynamic>),
      isMe: json['is_me'] as bool? ?? false,
      age: (json['age'] as num?)?.toInt(),
      stats: PlayerStats.fromJson(json['stats'] as Map<String, dynamic>),
      rating: PlayerRatingSummary.fromJson(json['rating'] as Map<String, dynamic>),
      topTags: [
        for (final item in (json['top_tags'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>())
          (
            label: item['label'] as String,
            positive: item['polarity'] == 'positive',
            count: (item['count'] as num).toInt(),
          ),
      ],
      teams: [
        for (final item in (json['teams'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>())
          PlayerTeam.fromJson(item),
      ],
      recentMatches: [
        for (final item in (json['recent_matches'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>())
          (
            id: (item['id'] as num).toInt(),
            startTime: DateTime.parse(item['start_time'] as String).toLocal(),
            sport: item['sport'] as String?,
            court: item['court'] as String?,
          ),
      ],
    );
  }
}

class PlayerStats {
  const PlayerStats({
    required this.matchesPlayed,
    required this.courtsPlayed,
    required this.hoursPlayed,
    required this.matchesOrganized,
    required this.upcomingMatches,
    this.sports = const [],
  });

  final int matchesPlayed;

  /// Different sports centers where they played.
  final int courtsPlayed;
  final double hoursPlayed;
  final int matchesOrganized;
  final int upcomingMatches;
  final List<({String name, int matches})> sports;

  factory PlayerStats.fromJson(Map<String, dynamic> json) {
    return PlayerStats(
      matchesPlayed: (json['matches_played'] as num).toInt(),
      courtsPlayed: (json['courts_played'] as num).toInt(),
      hoursPlayed: (json['hours_played'] as num).toDouble(),
      matchesOrganized: (json['matches_organized'] as num).toInt(),
      upcomingMatches: (json['upcoming_matches'] as num).toInt(),
      sports: [
        for (final item in (json['sports'] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>())
          (name: item['name'] as String? ?? '-', matches: (item['matches'] as num).toInt()),
      ],
    );
  }
}

class PlayerRatingSummary {
  const PlayerRatingSummary({
    required this.count,
    required this.noShows,
    this.average,
    this.attendanceRate,
    this.distribution = const {},
  });

  /// Average stars (1-5), null until someone rates them.
  final double? average;
  final int count;

  /// Stars → number of ratings.
  final Map<int, int> distribution;
  final int noShows;

  /// Percentage of rated matches they showed up to.
  final int? attendanceRate;

  factory PlayerRatingSummary.fromJson(Map<String, dynamic> json) {
    final raw = json['distribution'];
    final distribution = <int, int>{};
    if (raw is Map) {
      raw.forEach((key, value) => distribution[int.parse('$key')] = (value as num).toInt());
    } else if (raw is List) {
      // An empty PHP array arrives as [].
      for (var i = 0; i < raw.length; i++) {
        distribution[5 - i] = (raw[i] as num).toInt();
      }
    }
    return PlayerRatingSummary(
      average: (json['average'] as num?)?.toDouble(),
      count: (json['count'] as num).toInt(),
      distribution: distribution,
      noShows: (json['no_shows'] as num).toInt(),
      attendanceRate: (json['attendance_rate'] as num?)?.toInt(),
    );
  }
}

class PlayerTeam {
  const PlayerTeam({
    required this.id,
    required this.name,
    required this.membersCount,
    required this.isCaptain,
    this.shortName,
    this.primaryColor,
    this.logoUrl,
    this.sportName,
  });

  final int id;
  final String name;
  final String? shortName;
  final String? primaryColor;
  final String? logoUrl;
  final int membersCount;
  final bool isCaptain;
  final String? sportName;

  factory PlayerTeam.fromJson(Map<String, dynamic> json) {
    final sport = json['sport'] as Map<String, dynamic>?;
    return PlayerTeam(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      shortName: json['short_name'] as String?,
      primaryColor: json['primary_color'] as String?,
      logoUrl: json['logo_url'] as String?,
      membersCount: (json['members_count'] as num?)?.toInt() ?? 0,
      isCaptain: json['is_captain'] as bool? ?? false,
      sportName: sport?['name'] as String?,
    );
  }
}
