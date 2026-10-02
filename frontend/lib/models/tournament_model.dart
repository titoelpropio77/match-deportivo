import 'match_level_model.dart';
import 'match_model.dart';
import 'sport_model.dart';

/// Team as shown inside a tournament (teams list, fixture, standings).
class TournamentTeam {
  const TournamentTeam({
    required this.id,
    required this.name,
    this.shortName,
    this.primaryColor,
    this.logoUrl,
    this.ownerId,
    this.membersCount,
  });

  final int id;
  final String name;
  final String? shortName;
  final String? primaryColor;
  final String? logoUrl;
  final int? ownerId;
  final int? membersCount;

  String get initials {
    final short = shortName?.trim();
    if (short != null && short.isNotEmpty) return short.toUpperCase();
    final words = name.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
    return words.isEmpty ? '?' : words.first.substring(0, words.first.length.clamp(1, 2)).toUpperCase();
  }

  factory TournamentTeam.fromJson(Map<String, dynamic> json) {
    return TournamentTeam(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      shortName: json['short_name'] as String?,
      primaryColor: json['primary_color'] as String?,
      logoUrl: json['logo_url'] as String?,
      ownerId: (json['owner_id'] as num?)?.toInt(),
      membersCount: (json['members_count'] as num?)?.toInt(),
    );
  }
}

class TournamentRegistrationModel {
  const TournamentRegistrationModel({
    required this.id,
    required this.status,
    required this.amount,
    required this.paymentReference,
    required this.tournamentId,
    this.paymentExpiresAt,
    this.paidAt,
    this.cancellationReason,
    this.refunded = false,
    this.team,
  });

  final int id;

  /// pending_payment, confirmed, cancelled or expired.
  final String status;
  final double amount;
  final String paymentReference;
  final int tournamentId;
  final DateTime? paymentExpiresAt;
  final DateTime? paidAt;
  final String? cancellationReason;
  final bool refunded;
  final TournamentTeam? team;

  bool get isConfirmed => status == 'confirmed';
  bool get isPendingPayment => status == 'pending_payment';
  bool get isCancelled => status == 'cancelled';

  factory TournamentRegistrationModel.fromJson(Map<String, dynamic> json) {
    return TournamentRegistrationModel(
      id: (json['id'] as num).toInt(),
      status: json['status'] as String,
      amount: (json['amount'] as num).toDouble(),
      paymentReference: json['payment_reference'] as String,
      tournamentId: (json['tournament_id'] as num).toInt(),
      paymentExpiresAt: DateTime.tryParse(json['payment_expires_at'] as String? ?? '')?.toLocal(),
      paidAt: DateTime.tryParse(json['paid_at'] as String? ?? '')?.toLocal(),
      cancellationReason: json['cancellation_reason'] as String?,
      refunded: json['refunded'] as bool? ?? false,
      team: json['team'] is Map<String, dynamic> ? TournamentTeam.fromJson(json['team'] as Map<String, dynamic>) : null,
    );
  }
}

class TournamentGameModel {
  const TournamentGameModel({
    required this.id,
    required this.round,
    required this.status,
    this.scheduledAt,
    this.field,
    this.homeTeam,
    this.awayTeam,
    this.homeScore,
    this.awayScore,
  });

  final int id;
  final String round;

  /// scheduled, played or cancelled.
  final String status;
  final DateTime? scheduledAt;
  final String? field;
  final TournamentTeam? homeTeam;
  final TournamentTeam? awayTeam;
  final int? homeScore;
  final int? awayScore;

  bool get isPlayed => status == 'played' && homeScore != null && awayScore != null;

  factory TournamentGameModel.fromJson(Map<String, dynamic> json) {
    TournamentTeam? team(Object? value) => value is Map<String, dynamic> ? TournamentTeam.fromJson(value) : null;
    return TournamentGameModel(
      id: (json['id'] as num).toInt(),
      round: json['round'] as String,
      status: json['status'] as String,
      scheduledAt: DateTime.tryParse(json['scheduled_at'] as String? ?? '')?.toLocal(),
      field: json['field'] as String?,
      homeTeam: team(json['home_team']),
      awayTeam: team(json['away_team']),
      homeScore: (json['home_score'] as num?)?.toInt(),
      awayScore: (json['away_score'] as num?)?.toInt(),
    );
  }
}

class StandingRow {
  const StandingRow({
    required this.team,
    required this.played,
    required this.won,
    required this.drawn,
    required this.lost,
    required this.goalsFor,
    required this.goalsAgainst,
    required this.goalDifference,
    required this.points,
  });

  final TournamentTeam team;
  final int played;
  final int won;
  final int drawn;
  final int lost;
  final int goalsFor;
  final int goalsAgainst;
  final int goalDifference;
  final int points;

  factory StandingRow.fromJson(Map<String, dynamic> json) {
    int value(String key) => (json[key] as num).toInt();
    return StandingRow(
      team: TournamentTeam(
        id: value('team_id'),
        name: json['team'] as String,
        shortName: json['short_name'] as String?,
        primaryColor: json['primary_color'] as String?,
        logoUrl: json['logo_url'] as String?,
      ),
      played: value('played'),
      won: value('won'),
      drawn: value('drawn'),
      lost: value('lost'),
      goalsFor: value('goals_for'),
      goalsAgainst: value('goals_against'),
      goalDifference: value('goal_difference'),
      points: value('points'),
    );
  }
}

class TournamentModel {
  const TournamentModel({
    required this.id,
    required this.name,
    required this.format,
    required this.formatLabel,
    required this.entryFee,
    required this.maxTeams,
    required this.teamsCount,
    required this.spotsLeft,
    required this.minPlayersPerTeam,
    required this.status,
    required this.acceptsRegistrations,
    required this.registrationClosesAt,
    required this.startsOn,
    this.gender = MatchGender.mixed,
    this.endsOn,
    this.maxPlayersPerTeam,
    this.prizes,
    this.coverUrl,
    this.sport,
    this.level,
    this.venueName,
    this.venueAddress,
    this.cityName,
    this.description,
    this.rules,
    this.teams = const [],
    this.games = const [],
    this.standings = const [],
    this.myRegistrations = const [],
  });

  final int id;
  final String name;
  final String format;
  final String formatLabel;
  final MatchGender gender;
  final double entryFee;
  final int maxTeams;
  final int teamsCount;
  final int spotsLeft;
  final int minPlayersPerTeam;
  final int? maxPlayersPerTeam;

  /// open, closed, in_progress, finished or cancelled.
  final String status;
  final bool acceptsRegistrations;
  final DateTime registrationClosesAt;
  final DateTime startsOn;
  final DateTime? endsOn;
  final String? prizes;
  final String? coverUrl;
  final SportModel? sport;
  final MatchLevelModel? level;
  final String? venueName;
  final String? venueAddress;
  final String? cityName;

  // Detail only.
  final String? description;
  final String? rules;
  final List<TournamentTeam> teams;
  final List<TournamentGameModel> games;
  final List<StandingRow> standings;
  final List<TournamentRegistrationModel> myRegistrations;

  bool get isFree => entryFee <= 0;

  String get statusLabel => switch (status) {
        'open' => 'Inscripciones abiertas',
        'closed' => 'Inscripciones cerradas',
        'in_progress' => 'En curso',
        'finished' => 'Finalizado',
        'cancelled' => 'Cancelado',
        _ => status,
      };

  factory TournamentModel.fromJson(Map<String, dynamic> json) {
    final venue = json['venue'] as Map<String, dynamic>?;
    List<T> list<T>(String key, T Function(Map<String, dynamic>) parse) =>
        (json[key] as List<dynamic>? ?? const []).cast<Map<String, dynamic>>().map(parse).toList();

    return TournamentModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      format: json['format'] as String,
      formatLabel: json['format_label'] as String? ?? json['format'] as String,
      gender: json['gender'] == null ? MatchGender.mixed : MatchGender.fromValue(json['gender'] as String),
      entryFee: (json['entry_fee'] as num).toDouble(),
      maxTeams: (json['max_teams'] as num).toInt(),
      teamsCount: (json['teams_count'] as num).toInt(),
      spotsLeft: (json['spots_left'] as num).toInt(),
      minPlayersPerTeam: (json['min_players_per_team'] as num).toInt(),
      maxPlayersPerTeam: (json['max_players_per_team'] as num?)?.toInt(),
      status: json['status'] as String,
      acceptsRegistrations: json['accepts_registrations'] as bool? ?? false,
      registrationClosesAt: DateTime.parse(json['registration_closes_at'] as String).toLocal(),
      startsOn: DateTime.parse(json['starts_on'] as String),
      endsOn: DateTime.tryParse(json['ends_on'] as String? ?? ''),
      prizes: json['prizes'] as String?,
      coverUrl: json['cover_url'] as String?,
      sport: json['sport'] is Map<String, dynamic> ? SportModel.fromJson(json['sport'] as Map<String, dynamic>) : null,
      level: json['level'] is Map<String, dynamic> ? MatchLevelModel.fromJson(json['level'] as Map<String, dynamic>) : null,
      venueName: venue?['name'] as String?,
      venueAddress: venue?['address'] as String?,
      cityName: venue?['city'] as String?,
      description: json['description'] as String?,
      rules: json['rules'] as String?,
      teams: list('teams', TournamentTeam.fromJson),
      games: list('games', TournamentGameModel.fromJson),
      standings: list('standings', StandingRow.fromJson),
      myRegistrations: list('my_registrations', TournamentRegistrationModel.fromJson),
    );
  }
}

/// "Mis torneos": a tournament with the registration of the user's team.
class MyTournamentEntry {
  const MyTournamentEntry({required this.tournament, required this.registration});

  final TournamentModel tournament;
  final TournamentRegistrationModel registration;

  factory MyTournamentEntry.fromJson(Map<String, dynamic> json) => MyTournamentEntry(
        tournament: TournamentModel.fromJson(json['tournament'] as Map<String, dynamic>),
        registration: TournamentRegistrationModel.fromJson(json['registration'] as Map<String, dynamic>),
      );
}
