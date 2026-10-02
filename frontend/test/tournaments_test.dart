import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/models/team_model.dart';
import 'package:frontend/models/tournament_model.dart';
import 'package:frontend/screens/tournaments/tournaments_screen.dart';
import 'package:frontend/services/team_api_service.dart';
import 'package:frontend/services/tournament_api_service.dart';

const futbol = SportModel(id: 1, key: 'futbol_5', name: 'Fútbol 5');

Map<String, dynamic> tournamentJson({
  int id = 1,
  String status = 'open',
  bool accepts = true,
  List<Map<String, dynamic>> myRegistrations = const [],
  bool detail = false,
}) =>
    {
      'id': id,
      'name': 'Copa Primavera',
      'format': 'league',
      'format_label': 'Liga (todos contra todos)',
      'gender': 'mixed',
      'entry_fee': 200,
      'prizes': '1.º lugar: trofeo + Bs 1000',
      'max_teams': 8,
      'teams_count': 2,
      'spots_left': 6,
      'min_players_per_team': 3,
      'max_players_per_team': 10,
      'registration_closes_at': '2030-10-10T18:00:00Z',
      'starts_on': '2030-10-12',
      'ends_on': '2030-11-30',
      'status': status,
      'accepts_registrations': accepts,
      'cover_url': null,
      'sport': {'id': 1, 'key': 'futbol_5', 'name': 'Fútbol 5'},
      'level': null,
      'venue': {'id': 1, 'name': 'Arena Norte', 'address': 'Zona Norte', 'city': 'Santa Cruz'},
      if (detail) ...{
        'description': 'Torneo de los jueves.',
        'rules': 'Partidos de 40 minutos.',
        'teams': [
          {'id': 10, 'name': 'Los Tigres', 'short_name': 'TIG', 'primary_color': '#FB8C00', 'logo_url': null, 'members_count': 6},
          {'id': 11, 'name': 'Águilas', 'short_name': null, 'primary_color': null, 'logo_url': null, 'members_count': 5},
        ],
        'games': [
          {
            'id': 1,
            'round': 'Fecha 1',
            'round_order': 1,
            'scheduled_at': '2030-10-12T23:00:00Z',
            'field': 'Cancha 1',
            'status': 'played',
            'home_team': {'id': 10, 'name': 'Los Tigres'},
            'away_team': {'id': 11, 'name': 'Águilas'},
            'home_score': 3,
            'away_score': 1,
          },
        ],
        'standings': [
          {'team_id': 10, 'team': 'Los Tigres', 'played': 1, 'won': 1, 'drawn': 0, 'lost': 0, 'goals_for': 3, 'goals_against': 1, 'goal_difference': 2, 'points': 3},
          {'team_id': 11, 'team': 'Águilas', 'played': 1, 'won': 0, 'drawn': 0, 'lost': 1, 'goals_for': 1, 'goals_against': 3, 'goal_difference': -2, 'points': 0},
        ],
        'my_registrations': myRegistrations,
      },
    };

Map<String, dynamic> registrationJson(String status) => {
      'id': 5,
      'status': status,
      'amount': 200,
      'payment_reference': 'TR-000005',
      'payment_expires_at': DateTime.now().add(const Duration(minutes: 30)).toUtc().toIso8601String(),
      'paid_at': null,
      'tournament_id': 1,
      'team': {'id': 20, 'name': 'Mi Equipo', 'owner_id': 1},
    };

void main() {
  void bigScreen(WidgetTester tester) {
    tester.view.physicalSize = const Size(1080, 3200);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
  }

  Widget app(_FakeTournamentApi api, {_FakeTeamApi? teams}) => MaterialApp(
        home: TournamentsScreen(tournamentApiService: api, teamApiService: teams ?? _FakeTeamApi(), currentUserId: 1),
      );

  testWidgets('lists tournaments with fee, spots and deadline', (tester) async {
    bigScreen(tester);
    await tester.pumpWidget(app(_FakeTournamentApi()));
    await tester.pumpAndSettle();

    expect(find.text('Copa Primavera'), findsOneWidget);
    expect(find.text('Bs 200 / equipo'), findsOneWidget);
    expect(find.text('Inscripciones abiertas'), findsOneWidget);
    expect(find.text('2 de 8 equipos · quedan 6 cupos'), findsOneWidget);
    expect(find.textContaining('Inscripciones hasta el 10 oct'), findsOneWidget);
    expect(find.text('Fútbol 5 · Liga (todos contra todos) · Mixto'), findsOneWidget);
  });

  testWidgets('detail shows info, teams, fixture and standings', (tester) async {
    bigScreen(tester);
    await tester.pumpWidget(app(_FakeTournamentApi()));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Copa Primavera'));
    await tester.pumpAndSettle();

    expect(find.text('Bs 200 por equipo'), findsOneWidget);
    expect(find.text('1.º lugar: trofeo + Bs 1000'), findsOneWidget);
    expect(find.text('3 a 10 jugadores'), findsOneWidget);
    expect(find.text('Inscribir mi equipo · Bs 200'), findsOneWidget);

    await tester.tap(find.text('Equipos'));
    await tester.pumpAndSettle();
    expect(find.text('Los Tigres'), findsOneWidget);
    expect(find.text('6 jugadores'), findsOneWidget);

    await tester.tap(find.text('Fixture'));
    await tester.pumpAndSettle();
    expect(find.text('Fecha 1'), findsOneWidget);
    expect(find.text('3 – 1'), findsOneWidget);

    await tester.tap(find.text('Tabla'));
    await tester.pumpAndSettle();
    expect(find.text('PTS'), findsOneWidget);
    expect(find.text('+2'), findsOneWidget);
  });

  testWidgets('a captain registers a team, pays with the QR and sees it confirmed', (tester) async {
    bigScreen(tester);
    final api = _FakeTournamentApi();
    await tester.pumpWidget(app(api));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Copa Primavera'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Inscribir mi equipo · Bs 200'));
    await tester.pumpAndSettle();

    // Teams that cannot sign up say why.
    expect(find.text('Solo el capitán puede inscribirlo'), findsOneWidget);
    expect(find.text('Necesita al menos 3 jugadores (tiene 2)'), findsOneWidget);
    await tester.tap(find.text('Mi Equipo'));
    await tester.pumpAndSettle();

    expect(api.registeredTeamId, 20);
    expect(find.text('Pago de inscripción'), findsOneWidget);
    expect(find.text('Referencia: TR-000005'), findsOneWidget);
    expect(find.textContaining('Tu cupo queda apartado por 2'), findsOneWidget);

    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();
    expect(find.text('¡Equipo inscrito!'), findsOneWidget);
    await tester.tap(find.text('Listo'));
    await tester.pumpAndSettle();

    expect(api.paidId, 5);
    expect(find.text('Mi Equipo · Inscripción confirmada'), findsOneWidget);
    expect(find.text('Inscribir mi equipo · Bs 200'), findsNothing);
  });

  testWidgets('"Mis torneos" lists the tournaments of my teams', (tester) async {
    bigScreen(tester);
    await tester.pumpWidget(app(_FakeTournamentApi()));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mis torneos'));
    await tester.pumpAndSettle();

    expect(find.text('Copa Primavera'), findsOneWidget);
    expect(find.text('Mi Equipo · Falta pagar la inscripción'), findsOneWidget);
  });
}

class _FakeTournamentApi extends TournamentApiService {
  _FakeTournamentApi() : super(baseUrl: 'http://test');

  int? registeredTeamId;
  int? paidId;

  @override
  Future<List<TournamentModel>> list({String scope = 'upcoming', int? sportId}) async =>
      scope == 'finished' ? const [] : [TournamentModel.fromJson(tournamentJson())];

  @override
  Future<TournamentModel> show(int id) async => TournamentModel.fromJson(tournamentJson(
        detail: true,
        myRegistrations: paidId != null ? [registrationJson('confirmed')] : const [],
      ));

  @override
  Future<List<MyTournamentEntry>> mine() async => [
        MyTournamentEntry(
          tournament: TournamentModel.fromJson(tournamentJson()),
          registration: TournamentRegistrationModel.fromJson(registrationJson('pending_payment')),
        ),
      ];

  @override
  Future<TournamentRegistrationModel> register(int tournamentId, int teamId) async {
    registeredTeamId = teamId;
    return TournamentRegistrationModel.fromJson(registrationJson('pending_payment'));
  }

  @override
  Future<TournamentRegistrationModel> pay(int registrationId) async {
    paidId = registrationId;
    return TournamentRegistrationModel.fromJson(registrationJson('confirmed'));
  }
}

class _FakeTeamApi extends TeamApiService {
  _FakeTeamApi() : super(baseUrl: 'http://test');

  @override
  Future<List<TeamModel>> myTeams({int? sportId}) async => const [
        TeamModel(id: 20, name: 'Mi Equipo', ownerId: 1, sport: futbol, membersCount: 5, gender: MatchGender.mixed),
        TeamModel(id: 21, name: 'Equipo Ajeno', ownerId: 99, sport: futbol, membersCount: 5),
        TeamModel(id: 22, name: 'Equipo Chico', ownerId: 1, sport: futbol, membersCount: 2),
      ];
}
