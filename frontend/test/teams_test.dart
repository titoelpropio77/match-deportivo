import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/models/team_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/create_match_screen.dart';
import 'package:frontend/screens/teams/team_detail_screen.dart';
import 'package:frontend/screens/teams/team_form_screen.dart';
import 'package:frontend/screens/teams/teams_screen.dart';
import 'package:frontend/services/court_api_service.dart';
import 'package:frontend/services/match_api_service.dart';
import 'package:frontend/services/match_level_api_service.dart';
import 'package:frontend/services/sport_api_service.dart';
import 'package:frontend/services/team_api_service.dart';
import 'package:frontend/services/user_api_service.dart';

const me = UserModel(id: 1, name: 'Test User', email: 'me@test.com');
const ana = UserModel(id: 2, name: 'Ana Rojas', email: 'ana@test.com');
const beto = UserModel(id: 3, name: 'Beto Suárez', email: 'beto@test.com');
const futbol = SportModel(id: 1, key: 'futbol_5', name: 'Fútbol 5');

TeamModel tigres({List<TeamMemberModel>? members}) {
  final roster = members ??
      const [
        TeamMemberModel(id: 1, role: 'captain', user: me, jerseyNumber: 10, position: 'Delantero'),
        TeamMemberModel(id: 2, role: 'player', user: ana),
        TeamMemberModel(id: 3, role: 'player', user: beto, jerseyNumber: 1, position: 'Arquero'),
      ];
  return TeamModel(
    id: 5,
    name: 'Los Tigres',
    ownerId: me.id,
    shortName: 'TIG',
    sport: futbol,
    gender: MatchGender.male,
    primaryColor: '#FB8C00',
    membersCount: roster.length,
    members: roster,
  );
}

void main() {
  void bigScreen(WidgetTester tester) {
    tester.view.physicalSize = const Size(1080, 2600);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
  }

  testWidgets('empty teams list invites to create one, then lists teams with captain tag', (tester) async {
    bigScreen(tester);
    final api = _FakeTeamApi()..mine = [];

    await tester.pumpWidget(MaterialApp(home: _teamsScreen(api)));
    await tester.pumpAndSettle();

    expect(find.text('Todavía no tienes equipos'), findsOneWidget);
    expect(find.text('Crear mi primer equipo'), findsOneWidget);

    api.mine = [tigres()];
    await tester.fling(find.text('Todavía no tienes equipos'), const Offset(0, 400), 1000);
    await tester.pumpAndSettle();

    expect(find.text('Los Tigres'), findsOneWidget);
    expect(find.text('Fútbol 5 · Masculino'), findsOneWidget);
    expect(find.text('3 jugadores'), findsOneWidget);
    expect(find.text('Capitán'), findsOneWidget);
    expect(find.text('TIG'), findsOneWidget);
    expect(find.text('Crear equipo'), findsOneWidget);
  });

  testWidgets('create team form sends the details and the picked players', (tester) async {
    bigScreen(tester);
    final api = _FakeTeamApi()..mine = [];

    await tester.pumpWidget(MaterialApp(home: _teamsScreen(api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Crear mi primer equipo'));
    await tester.pumpAndSettle();

    expect(find.text('Tú serás el capitán. Puedes agregar jugadores ahora o después.'), findsOneWidget);

    await tester.tap(find.text('Crear equipo').last);
    await tester.pumpAndSettle();
    expect(find.text('Escribe el nombre del equipo.'), findsOneWidget);
    expect(find.text('Elige el deporte del equipo.'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextFormField, 'Nombre del equipo *'), 'Los Tigres');
    await tester.enterText(find.widgetWithText(TextFormField, 'Abreviatura'), 'tig');
    await tester.pump();
    // Live badge preview uses the abbreviation.
    expect(find.text('TIG'), findsOneWidget);

    await tester.tap(find.widgetWithText(DropdownButtonFormField<int>, 'Deporte *'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fútbol 5').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Masculino'));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Buscar por nombre o email'),
      300,
      scrollable: find.descendant(of: find.byType(TeamFormScreen), matching: find.byType(Scrollable)).first,
    );
    await tester.enterText(find.widgetWithText(TextField, 'Buscar por nombre o email'), 'ana');
    await tester.pump(const Duration(milliseconds: 450));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Ana Rojas'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ana Rojas'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(InputChip, 'Ana Rojas'), findsOneWidget);

    await tester.tap(find.text('Crear equipo').last);
    await tester.pumpAndSettle();

    expect(api.created, {
      'name': 'Los Tigres',
      'sport_id': 1,
      'short_name': 'TIG',
      'gender': 'male',
      'member_ids': [2],
    });
    // Opens the new team.
    expect(find.text('Plantel'), findsOneWidget);
  });

  testWidgets('captain manages the roster; a player can leave', (tester) async {
    bigScreen(tester);
    final api = _FakeTeamApi()..detail = tigres();

    await tester.pumpWidget(MaterialApp(home: _detailScreen(api, currentUserId: me.id)));
    await tester.pumpAndSettle();

    expect(find.text('Test User (tú)'), findsOneWidget);
    expect(find.text('Delantero'), findsOneWidget);
    expect(find.text('Arquero'), findsOneWidget);
    expect(find.text('Agregar jugador'), findsOneWidget);
    expect(find.byTooltip('Editar equipo'), findsOneWidget);
    expect(find.text('Salir del equipo'), findsNothing);

    await tester.pumpWidget(MaterialApp(home: _detailScreen(api, currentUserId: ana.id, key: UniqueKey())));
    await tester.pumpAndSettle();

    expect(find.text('Agregar jugador'), findsNothing);
    expect(find.byTooltip('Editar equipo'), findsNothing);
    await tester.tap(find.text('Salir del equipo'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Salir'));
    await tester.pumpAndSettle();

    expect(api.removed, (5, ana.id));
  });

  testWidgets('adding a team to a match adds all its players and sends the team', (tester) async {
    tester.view.physicalSize = const Size(800, 2600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final teams = _FakeTeamApi()
      ..mine = [tigres()]
      ..detail = tigres();

    await tester.pumpWidget(
      MaterialApp(
        home: CreateMatchScreen(
          matchApiService: MatchApiService(baseUrl: 'http://test'),
          userApiService: UserApiService(baseUrl: 'http://test'),
          sportApiService: _FakeSportApi(),
          matchLevelApiService: _FakeLevelApi(),
          courtApiService: CourtApiService(baseUrl: 'http://test'),
          teamApiService: teams,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Agregar equipos'), findsOneWidget);
    await tester.tap(find.text('Agregar equipo'));
    await tester.pumpAndSettle();

    expect(find.text('Equipos de Fútbol 5. Se agregarán todos sus jugadores al partido.'), findsOneWidget);
    await tester.tap(find.text('Los Tigres'));
    await tester.pumpAndSettle();

    expect(find.text('Los Tigres agregado: 3 jugadores más.'), findsOneWidget);
    expect(find.text('Test, Ana, Beto'), findsOneWidget);
    expect(find.text('3 jugadores agregados'), findsOneWidget);
    expect(find.widgetWithText(Chip, 'Beto Suárez'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextFormField, 'Límite de jugadores/equipos'), '2');
    await tester.pump();
    expect(find.text('3 de 2 jugadores'), findsOneWidget);
    expect(find.text('Superas el límite del partido: súbelo o quita jugadores.'), findsOneWidget);

    await tester.tap(find.byTooltip('Quitar equipo'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(Chip, 'Beto Suárez'), findsNothing);
  });

  test('creating a match sends the chosen teams', () async {
    String? body;
    final client = MockClient((request) async {
      body = request.body;
      return http.Response(
        '{"data":{"id":1,"organizer_id":1,"sport_id":1,"level_id":1,"court_id":1,'
        '"scheduled_at":"2026-10-02T18:00:00Z","start_time":"2026-10-02T18:00:00Z",'
        '"end_time":"2026-10-02T19:00:00Z","total_players":10,"missing_players":7,'
        '"max_players":10,"status":"open"}}',
        201,
      );
    });

    await MatchApiService(baseUrl: 'http://test', client: client).createMatch(
      sportId: 1,
      levelId: 1,
      courtId: 1,
      gender: 'mixed',
      startTime: DateTime(2026, 10, 2, 18),
      endTime: DateTime(2026, 10, 2, 19),
      maxPlayers: 10,
      teamIds: const [5, 8],
    );

    expect(body, contains('name="team_ids[0]"\r\n\r\n5'));
    expect(body, contains('name="team_ids[1]"\r\n\r\n8'));
  });

  test('parses the teams of a match', () {
    final match = MatchModel.fromJson({
      'id': 10,
      'organizer_id': 1,
      'sport_id': 1,
      'level_id': 1,
      'court_id': 1,
      'scheduled_at': '2026-10-01T18:00:00Z',
      'start_time': '2026-10-01T18:00:00Z',
      'end_time': '2026-10-01T20:00:00Z',
      'total_players': 8,
      'missing_players': 4,
      'max_players': 8,
      'status': 'open',
      'teams': [
        {'id': 5, 'name': 'Los Tigres', 'short_name': 'TIG', 'primary_color': '#FB8C00', 'logo_url': null, 'sport_id': 1},
      ],
    });

    expect(match.teams.single.name, 'Los Tigres');
    expect(match.teams.single.initials, 'TIG');
  });

  test('team initials come from the abbreviation or the name', () {
    TeamModel team(String name, [String? short]) => TeamModel(id: 1, name: name, ownerId: 1, shortName: short);
    expect(team('Los Tigres').initials, 'LT');
    expect(team('Tigres').initials, 'TI');
    expect(team('Los Tigres', 'tig').initials, 'TIG');
  });
}

Widget _teamsScreen(_FakeTeamApi api) => TeamsScreen(
      teamApiService: api,
      userApiService: _FakeUserApi(),
      sportApiService: _FakeSportApi(),
      matchLevelApiService: _FakeLevelApi(),
      currentUserId: me.id,
    );

Widget _detailScreen(_FakeTeamApi api, {required int currentUserId, Key? key}) => TeamDetailScreen(
      key: key,
      teamId: 5,
      teamApiService: api,
      userApiService: _FakeUserApi(),
      sportApiService: _FakeSportApi(),
      matchLevelApiService: _FakeLevelApi(),
      currentUserId: currentUserId,
    );

class _FakeTeamApi extends TeamApiService {
  _FakeTeamApi() : super(baseUrl: 'http://test');

  List<TeamModel> mine = const [];
  TeamModel? detail;
  Map<String, Object?>? created;
  (int, int)? removed;

  @override
  Future<List<TeamModel>> myTeams({int? sportId}) async => mine;

  @override
  Future<List<TeamModel>> search(String query, {int? sportId}) async => mine;

  @override
  Future<TeamModel> show(int id) async => detail ?? tigres();

  @override
  Future<TeamModel> create({
    required String name,
    required int sportId,
    String? shortName,
    int? levelId,
    String gender = 'mixed',
    String? primaryColor,
    String? description,
    List<int> memberIds = const [],
    TeamLogoUpload? logo,
  }) async {
    created = {
      'name': name,
      'sport_id': sportId,
      'short_name': shortName,
      'gender': gender,
      'member_ids': memberIds,
    };
    detail = tigres();
    mine = [tigres()];
    return tigres();
  }

  @override
  Future<TeamModel> removeMember(int teamId, int userId) async {
    removed = (teamId, userId);
    return tigres();
  }
}

class _FakeUserApi extends UserApiService {
  _FakeUserApi() : super(baseUrl: 'http://test');

  @override
  Future<List<UserModel>> search(String query) async => const [ana, beto];
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [futbol, SportModel(id: 2, key: 'padel', name: 'Pádel')];
}

class _FakeLevelApi extends MatchLevelApiService {
  _FakeLevelApi() : super(baseUrl: 'http://test');

  @override
  Future<List<MatchLevelModel>> list() async => const [MatchLevelModel(id: 1, key: 'basic', name: 'Básico', order: 1)];
}

