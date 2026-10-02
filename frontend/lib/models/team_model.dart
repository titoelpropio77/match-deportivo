import 'match_level_model.dart';
import 'match_model.dart';
import 'sport_model.dart';
import 'user_model.dart';

class TeamMemberModel {
  const TeamMemberModel({
    required this.id,
    required this.role,
    required this.user,
    this.jerseyNumber,
    this.position,
  });

  final int id;

  /// `captain` or `player`.
  final String role;
  final UserModel user;
  final int? jerseyNumber;
  final String? position;

  bool get isCaptain => role == 'captain';

  factory TeamMemberModel.fromJson(Map<String, dynamic> json) {
    return TeamMemberModel(
      id: (json['id'] as num).toInt(),
      role: json['role'] as String,
      user: UserModel.fromJson(json['user'] as Map<String, dynamic>),
      jerseyNumber: (json['jersey_number'] as num?)?.toInt(),
      position: json['position'] as String?,
    );
  }
}

class TeamModel {
  const TeamModel({
    required this.id,
    required this.name,
    required this.ownerId,
    this.shortName,
    this.sport,
    this.level,
    this.gender = MatchGender.mixed,
    this.primaryColor,
    this.description,
    this.logoUrl,
    this.membersCount = 0,
    this.isMember = false,
    this.members = const [],
  });

  final int id;
  final String name;
  final int ownerId;

  /// Up to 4 characters, e.g. "TIG".
  final String? shortName;
  final SportModel? sport;
  final MatchLevelModel? level;
  final MatchGender gender;

  /// "#RRGGBB".
  final String? primaryColor;
  final String? description;
  final String? logoUrl;
  final int membersCount;
  final bool isMember;

  /// Only filled by the detail endpoints.
  final List<TeamMemberModel> members;

  /// "TIG", or the initials of the name ("Los Tigres" → "LT").
  String get initials {
    final short = shortName?.trim();
    if (short != null && short.isNotEmpty) return short.toUpperCase();
    final words = name.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
    if (words.isEmpty) return '?';
    if (words.length == 1) return words.first.substring(0, words.first.length.clamp(1, 2)).toUpperCase();
    return (words[0][0] + words[1][0]).toUpperCase();
  }

  factory TeamModel.fromJson(Map<String, dynamic> json) {
    final members = (json['members'] as List<dynamic>? ?? const [])
        .map((item) => TeamMemberModel.fromJson(item as Map<String, dynamic>))
        .toList();
    return TeamModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      ownerId: (json['owner_id'] as num?)?.toInt() ?? 0,
      shortName: json['short_name'] as String?,
      sport: json['sport'] is Map<String, dynamic> ? SportModel.fromJson(json['sport'] as Map<String, dynamic>) : null,
      level: json['level'] is Map<String, dynamic>
          ? MatchLevelModel.fromJson(json['level'] as Map<String, dynamic>)
          : null,
      gender: json['gender'] == null ? MatchGender.mixed : MatchGender.fromValue(json['gender'] as String),
      primaryColor: json['primary_color'] as String?,
      description: json['description'] as String?,
      logoUrl: json['logo_url'] as String?,
      membersCount: (json['members_count'] as num?)?.toInt() ?? members.length,
      isMember: json['is_member'] as bool? ?? false,
      members: members,
    );
  }
}
