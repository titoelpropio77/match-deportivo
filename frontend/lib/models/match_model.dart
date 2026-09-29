import 'court_model.dart';
import 'match_level_model.dart';
import 'match_player_model.dart';
import 'sport_model.dart';
import 'user_model.dart';

enum MatchStatus {
  open('open'),
  full('full'),
  cancelled('cancelled'),
  finished('finished');

  const MatchStatus(this.value);

  final String value;

  static MatchStatus fromValue(String value) => MatchStatus.values.firstWhere(
        (status) => status.value == value,
        orElse: () => throw FormatException('Unknown match status: $value'),
      );
}

enum MatchGender {
  mixed('mixed', 'Mixto'),
  male('male', 'Masculino'),
  female('female', 'Femenino');

  const MatchGender(this.value, this.label);

  final String value;
  final String label;

  static MatchGender fromValue(String value) => MatchGender.values.firstWhere(
        (gender) => gender.value == value,
        orElse: () => throw FormatException('Unknown match gender: $value'),
      );
}

class MatchModel {
  const MatchModel({
    this.id,
    required this.organizerId,
    required this.sportId,
    required this.levelId,
    required this.courtId,
    this.gender = MatchGender.mixed,
    this.paymentQrUrl,
    this.sport,
    this.level,
    this.court,
    this.organizer,
    this.players,
    required this.scheduledAt,
    required this.startTime,
    required this.endTime,
    required this.totalPlayers,
    required this.missingPlayers,
    required this.maxPlayers,
    required this.status,
  });

  final int? id;
  final int organizerId;
  final int sportId;
  final int levelId;
  final int courtId;
  final MatchGender gender;
  final String? paymentQrUrl;
  final SportModel? sport;
  final MatchLevelModel? level;
  final CourtModel? court;
  final UserModel? organizer;
  final List<MatchPlayerModel>? players;
  final DateTime scheduledAt;
  final DateTime startTime;
  final DateTime endTime;
  final int totalPlayers;
  final int missingPlayers;
  final int maxPlayers;
  final MatchStatus status;

  bool get isFull => status == MatchStatus.full || missingPlayers <= 0;

  factory MatchModel.fromJson(Map<String, dynamic> json) {
    return MatchModel(
      id: (json['id'] as num?)?.toInt(),
      organizerId: (json['organizer_id'] as num).toInt(),
      sportId: (json['sport_id'] as num).toInt(),
      levelId: (json['level_id'] as num).toInt(),
      courtId: (json['court_id'] as num).toInt(),
      gender: json['gender'] == null
          ? MatchGender.mixed
          : MatchGender.fromValue(json['gender'] as String),
      paymentQrUrl: json['payment_qr_url'] as String?,
      sport: json['sport'] != null
          ? SportModel.fromJson(json['sport'] as Map<String, dynamic>)
          : null,
      level: json['level'] != null
          ? MatchLevelModel.fromJson(json['level'] as Map<String, dynamic>)
          : null,
      court: json['court'] != null
          ? CourtModel.fromJson(json['court'] as Map<String, dynamic>)
          : null,
      organizer: json['organizer'] != null
          ? UserModel.fromJson(json['organizer'] as Map<String, dynamic>)
          : null,
      players: json['players'] != null
          ? (json['players'] as List<dynamic>)
              .map((item) => MatchPlayerModel.fromJson(item as Map<String, dynamic>))
              .toList()
          : null,
      scheduledAt: DateTime.parse(json['scheduled_at'] as String),
      startTime: DateTime.parse(json['start_time'] as String),
      endTime: DateTime.parse(json['end_time'] as String),
      totalPlayers: (json['total_players'] as num).toInt(),
      missingPlayers: (json['missing_players'] as num).toInt(),
      maxPlayers: (json['max_players'] as num).toInt(),
      status: MatchStatus.fromValue(json['status'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'organizer_id': organizerId,
      'sport_id': sportId,
      'level_id': levelId,
      'court_id': courtId,
      'gender': gender.value,
      if (paymentQrUrl != null) 'payment_qr_url': paymentQrUrl,
      'scheduled_at': scheduledAt.toIso8601String(),
      'start_time': startTime.toIso8601String(),
      'end_time': endTime.toIso8601String(),
      'total_players': totalPlayers,
      'missing_players': missingPlayers,
      'max_players': maxPlayers,
      'status': status.value,
    };
  }
}
