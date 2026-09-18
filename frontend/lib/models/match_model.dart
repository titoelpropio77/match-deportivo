enum MatchSport {
  football5('football_5'),
  football7('football_7'),
  padel('padel');

  const MatchSport(this.value);

  final String value;

  static MatchSport fromValue(String value) => MatchSport.values.firstWhere(
        (sport) => sport.value == value,
        orElse: () => throw FormatException('Unknown match sport: $value'),
      );
}

enum MatchStatus {
  open('open'),
  full('full'),
  cancelled('cancelled');

  const MatchStatus(this.value);

  final String value;

  static MatchStatus fromValue(String value) => MatchStatus.values.firstWhere(
        (status) => status.value == value,
        orElse: () => throw FormatException('Unknown match status: $value'),
      );
}

enum MatchLevel {
  basico('básico'),
  basicoIntermedio('básico/intermedio'),
  intermedio('intermedio'),
  intermedioAvanzado('intermedio avanzado'),
  avanzado('avanzado'),
  elite('élite');

  const MatchLevel(this.value);

  final String value;

  static MatchLevel fromValue(String value) => MatchLevel.values.firstWhere(
        (level) => level.value == value,
        orElse: () => throw FormatException('Unknown match level: $value'),
      );
}

class MatchModel {
  const MatchModel({
    this.id,
    required this.organizerId,
    required this.sport,
    required this.level,
    required this.location,
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
  final MatchSport sport;
  final MatchLevel level;
  final String location;
  final DateTime scheduledAt;
  final DateTime startTime;
  final DateTime endTime;
  final int totalPlayers;
  final int missingPlayers;
  final int maxPlayers;
  final MatchStatus status;

  factory MatchModel.fromJson(Map<String, dynamic> json) {
    return MatchModel(
      id: (json['id'] as num?)?.toInt(),
      organizerId: (json['organizer_id'] as num).toInt(),
      sport: MatchSport.fromValue(json['sport'] as String),
      level: MatchLevel.fromValue(json['level'] as String),
      location: json['location'] as String,
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
      'sport': sport.value,
      'level': level.value,
      'location': location,
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