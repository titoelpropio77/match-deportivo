import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/match_player_model.dart';
import 'package:frontend/models/player_profile_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/profile/player_profile_screen.dart';
import 'package:frontend/screens/search_teams/widgets/match_players_list.dart';
import 'package:frontend/services/user_api_service.dart';

Map<String, dynamic> profileJson({bool isMe = false, bool empty = false}) => {
      'user': {
        'id': 7,
        'name': 'Carlos Mamani',
        'nickname': empty ? null : 'Charly',
        if (isMe) 'email': 'carlos@test.com',
        'gender': 'male',
        'preferred_position': empty ? null : 'Delantero',
        'birth_date': empty ? null : '1995-06-15',
        'photo_url': null,
        'created_at': '2026-09-18T10:00:00Z',
      },
      'is_me': isMe,
      'age': empty ? null : 31,
      'stats': {
        'matches_played': empty ? 0 : 12,
        'courts_played': empty ? 0 : 3,
        'hours_played': empty ? 0 : 18.5,
        'matches_organized': empty ? 0 : 2,
        'upcoming_matches': empty ? 0 : 1,
        'sports': empty
            ? []
            : [
                {'name': 'Fútbol 5', 'matches': 9},
                {'name': 'Wally', 'matches': 3},
              ],
      },
      'rating': {
        'average': empty ? null : 4.5,
        'count': empty ? 0 : 10,
        'distribution': empty ? [] : {'5': 6, '4': 3, '3': 1, '2': 0, '1': 0},
        'no_shows': empty ? 0 : 1,
        'attendance_rate': empty ? null : 92,
      },
      'top_tags': empty
          ? []
          : [
              {'label': 'Crack', 'polarity': 'positive', 'count': 5},
              {'label': 'Llegó tarde', 'polarity': 'negative', 'count': 1},
            ],
      'teams': empty
          ? []
          : [
              {
                'id': 5,
                'name': 'Los Tigres',
                'short_name': 'TIG',
                'primary_color': '#FB8C00',
                'logo_url': null,
                'members_count': 6,
                'is_captain': true,
                'sport': {'id': 1, 'name': 'Fútbol 5'},
              },
            ],
      'recent_matches': empty
          ? []
          : [
              {'id': 3, 'start_time': '2026-09-30T22:00:00Z', 'sport': 'Fútbol 5', 'court': 'Arena Norte'},
            ],
    };

void main() {
  void bigScreen(WidgetTester tester) {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
  }

  testWidgets('shows the player summary, stats, rating, tags and teams', (tester) async {
    bigScreen(tester);
    final api = _FakeUserApi(profileJson());

    await tester.pumpWidget(MaterialApp(home: PlayerProfileScreen(userId: 7, userApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Perfil del jugador'), findsOneWidget);
    expect(find.text('Charly'), findsOneWidget);
    expect(find.text('Carlos Mamani'), findsOneWidget);
    expect(find.text('31 años'), findsOneWidget);
    expect(find.text('Masculino'), findsOneWidget);
    expect(find.text('Delantero'), findsOneWidget);
    expect(find.text('Desde sep 2026'), findsOneWidget);

    expect(find.text('4.5'), findsOneWidget);
    expect(find.text('10 calificaciones'), findsOneWidget);
    expect(find.text('Asistencia 92%'), findsOneWidget);
    expect(find.text('1 falta'), findsOneWidget);

    expect(find.text('12'), findsOneWidget);
    expect(find.text('Partidos jugados'), findsOneWidget);
    expect(find.text('Canchas distintas'), findsOneWidget);
    expect(find.text('18.5'), findsOneWidget);

    expect(find.text('Crack ×5'), findsOneWidget);
    expect(find.text('Llegó tarde'), findsOneWidget);
    expect(find.text('9 partidos'), findsOneWidget);
    expect(find.text('Los Tigres'), findsOneWidget);
    expect(find.text('Fútbol 5 · 6 jugadores · Capitán'), findsOneWidget);
    expect(find.text('Fútbol 5 · Arena Norte'), findsOneWidget);
    // Not your profile: no editing.
    expect(find.byTooltip('Editar perfil'), findsNothing);
  });

  testWidgets('a new player shows friendly empty states; your own profile can be completed', (tester) async {
    bigScreen(tester);
    final api = _FakeUserApi(profileJson(isMe: true, empty: true));

    await tester.pumpWidget(MaterialApp(home: PlayerProfileScreen(userId: 7, userApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Mi perfil de jugador'), findsOneWidget);
    expect(find.textContaining('Todavía no tiene calificaciones'), findsOneWidget);
    expect(find.text('Completa tu perfil'), findsOneWidget);
    expect(find.text('Lo que destacan sus compañeros'), findsNothing);

    await tester.tap(find.byTooltip('Editar perfil'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'Apodo'), 'Charly');
    await tester.enterText(find.widgetWithText(TextField, 'Posición preferida'), 'Arquero');
    await tester.tap(find.text('Guardar'));
    await tester.pumpAndSettle();

    expect(api.updated, {'nickname': 'Charly', 'position': 'Arquero', 'gender': 'male'});
    expect(find.text('Perfil actualizado.'), findsOneWidget);
    expect(api.loads, 2);
  });

  testWidgets('tapping a player in the match opens their profile', (tester) async {
    int? opened;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MatchPlayersList(
            organizerName: 'Test User',
            organizerUserId: 1,
            players: [
              MatchPlayerModel.fromJson({
                'id': 1,
                'match_id': 1,
                'user_id': 7,
                'quantity_slots': 1,
                'status': 'confirmed',
                'user': {'id': 7, 'name': 'Carlos Mamani', 'nickname': 'Charly', 'email': 'c@test.com'},
              }),
            ],
            isOrganizer: false,
            isFull: false,
            onOpenProfile: (id) => opened = id,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Charly'));
    expect(opened, 7);
    await tester.tap(find.text('Test User'));
    expect(opened, 1);
  });

  test('parses an empty rating distribution sent as []', () {
    final profile = PlayerProfileModel.fromJson(profileJson(empty: true));
    expect(profile.rating.distribution, isEmpty);
    expect(profile.rating.average, isNull);
  });
}

class _FakeUserApi extends UserApiService {
  _FakeUserApi(this.json) : super(baseUrl: 'http://test');

  final Map<String, dynamic> json;
  Map<String, Object?>? updated;
  int loads = 0;

  @override
  Future<PlayerProfileModel> profile(int userId) async {
    loads++;
    return PlayerProfileModel.fromJson(json);
  }

  @override
  Future<UserModel> updateMe({
    String? nickname,
    String? phone,
    String? preferredPosition,
    DateTime? birthDate,
    String? gender,
    List<int>? favoriteSportIds,
  }) async {
    updated = {'nickname': nickname, 'position': preferredPosition, 'gender': gender};
    return UserModel.fromJson(json['user'] as Map<String, dynamic>);
  }
}
