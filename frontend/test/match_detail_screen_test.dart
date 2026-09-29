import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/match_level_model.dart';
import 'package:frontend/models/match_model.dart';
import 'package:frontend/models/match_player_model.dart';
import 'package:frontend/models/rating_tag_model.dart';
import 'package:frontend/models/sport_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/search_teams/match_detail_screen.dart';
import 'package:frontend/services/match_api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final start = DateTime.now().add(const Duration(hours: 2));
  final end = DateTime.now().add(const Duration(hours: 3));

  MatchModel buildMatch({
    required int organizerId,
    List<MatchPlayerModel> players = const [],
    int missingPlayers = 9,
    MatchStatus status = MatchStatus.open,
    String? paymentQrUrl,
    DateTime? startTime,
    DateTime? endTime,
  }) {
    final matchStart = startTime ?? start;
    final matchEnd = endTime ?? end;
    return MatchModel(
      id: 1,
      organizerId: organizerId,
      sportId: 1,
      levelId: 1,
      courtId: 1,
      paymentQrUrl: paymentQrUrl,
      sport: const SportModel(id: 1, key: 'football', name: 'Fútbol 5'),
      level: const MatchLevelModel(
        id: 1,
        key: 'intermediate',
        name: 'Intermedio',
        order: 2,
      ),
      organizer: UserModel(
        id: organizerId,
        name: 'Test User',
        email: 'test@example.com',
        nickname: 'Test User',
      ),
      players: players,
      scheduledAt: matchStart,
      startTime: matchStart,
      endTime: matchEnd,
      totalPlayers: 10,
      missingPlayers: missingPlayers,
      maxPlayers: 10,
      status: status,
    );
  }

  Future<void> pumpDetail(
    WidgetTester tester, {
    required MatchModel match,
    required int currentUserId,
    _FakeMatchApiService? api,
  }) async {
    final service = api ?? _FakeMatchApiService(match);
    await tester.pumpWidget(
      MaterialApp(
        home: MatchDetailScreen(
          matchId: 1,
          matchApiService: service,
          currentUserId: currentUserId,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('shows the payment QR to the organizer', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        paymentQrUrl: 'https://example.com/payment-qr.png',
      ),
      currentUserId: 10,
    );

    expect(find.text('Pago de la cancha'), findsOneWidget);
  });

  testWidgets('hides the payment QR when the match has none', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(organizerId: 10),
      currentUserId: 20,
    );

    expect(find.text('Pago de la cancha'), findsNothing);
  });

  testWidgets('shows Unirse al equipo when the user is not in the match', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      match: buildMatch(organizerId: 10),
      currentUserId: 20,
    );

    expect(find.text('Unirse al equipo'), findsOneWidget);
    expect(find.text('Compartir'), findsOneWidget);
    expect(find.text('Salir del partido'), findsNothing);
    expect(find.text('Eliminar'), findsNothing);
  });

  testWidgets('shows Unirse al equipo when the organizer is not a registered player', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      match: buildMatch(organizerId: 10),
      currentUserId: 10,
    );

    expect(find.text('Unirse al equipo'), findsOneWidget);
    expect(find.text('Salir del partido'), findsNothing);
    expect(find.text('Eliminar'), findsOneWidget);
  });

  testWidgets('hides Eliminar cancha after the match has started', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        startTime: DateTime.now().subtract(const Duration(hours: 1)),
        endTime: DateTime.now().add(const Duration(hours: 1)),
      ),
      currentUserId: 10,
    );

    expect(find.text('Eliminar'), findsNothing);
  });

  testWidgets('shows Salir del partido when the user is already joined', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        missingPlayers: 8,
        players: const [
          MatchPlayerModel(id: 1, userId: 20, quantitySlots: 1),
        ],
      ),
      currentUserId: 20,
    );

    expect(find.text('Salir del partido'), findsOneWidget);
    expect(find.text('Unirse al equipo'), findsNothing);
  });

  testWidgets('joining a match sends a pending request', (tester) async {
    const currentUserId = 20;
    final api = _FakeMatchApiService(buildMatch(organizerId: 10));

    await pumpDetail(
      tester,
      match: api.match,
      currentUserId: currentUserId,
      api: api,
    );

    await tester.tap(find.text('Unirse al equipo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirmar'));
    await tester.pumpAndSettle();

    expect(api.joinCalled, isTrue);
    expect(find.text('Cancelar solicitud'), findsOneWidget);
    expect(find.text('Solicitud enviada. El organizador debe confirmarte.'), findsOneWidget);
  });

  testWidgets('organizer can review a pending player', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        players: [
          MatchPlayerModel(
            id: 1,
            userId: 30,
            quantitySlots: 1,
            status: MatchPlayerStatus.pending,
            user: const UserModel(
              id: 30,
              name: 'Anita Rojas',
              email: 'anita@example.com',
              nickname: 'Anita',
            ),
          ),
        ],
      ),
      currentUserId: 10,
    );

    expect(find.text('Agregar jugador'), findsOneWidget);
    expect(find.text('Ver jugador'), findsOneWidget);
    await tester.tap(find.text('Ver jugador'));
    await tester.pumpAndSettle();

    expect(find.text('Aceptar por esta vez'), findsOneWidget);
    expect(find.text('Aceptar por siempre a este jugador'), findsOneWidget);
    expect(find.text('Rechazar'), findsOneWidget);
  });

  testWidgets('organizer can remove a confirmed player', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        players: [
          MatchPlayerModel(
            id: 1,
            userId: 30,
            quantitySlots: 1,
            user: const UserModel(
              id: 30,
              name: 'Anita Rojas',
              email: 'anita@example.com',
              nickname: 'Anita',
            ),
          ),
        ],
      ),
      currentUserId: 10,
    );

    expect(find.text('Agregar jugador'), findsOneWidget);
    expect(find.text('Quitar jugador'), findsOneWidget);
    await tester.tap(find.text('Quitar jugador'));
    await tester.pumpAndSettle();
    expect(find.text('¿Confirmas que quieres quitar a Anita de este partido?'), findsOneWidget);
  });

  testWidgets('organizer can open the add player search', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(organizerId: 10),
      currentUserId: 10,
    );

    await tester.tap(find.text('Agregar jugador'));
    await tester.pumpAndSettle();
    expect(find.text('Buscar por nickname, nombre o correo'), findsOneWidget);
  });

  testWidgets('when the match is full the organizer can add players to reserve', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        missingPlayers: 0,
        status: MatchStatus.full,
        players: [
          const MatchPlayerModel(
            id: 1,
            userId: 30,
            quantitySlots: 1,
            user: UserModel(
              id: 30,
              name: 'Anita Rojas',
              email: 'anita@example.com',
              nickname: 'Anita',
            ),
          ),
          const MatchPlayerModel(
            id: 2,
            userId: 40,
            quantitySlots: 1,
            status: MatchPlayerStatus.reserved,
            user: UserModel(
              id: 40,
              name: 'Carlos Mamani',
              email: 'carlos@example.com',
              nickname: 'Charly',
            ),
          ),
        ],
      ),
      currentUserId: 10,
    );

    expect(find.text('Agregar jugador'), findsNothing);
    expect(find.text('Jugadores en reserva'), findsOneWidget);
    expect(find.text('Agregar a reserva'), findsOneWidget);
    expect(find.text('Charly'), findsOneWidget);
    expect(find.text('Reserva #1'), findsOneWidget);

    await tester.ensureVisible(find.text('Agregar a reserva'));
    await tester.tap(find.text('Agregar a reserva'));
    await tester.pumpAndSettle();
    expect(find.text('Buscar por nickname, nombre o correo'), findsOneWidget);
  });

  testWidgets('shows Unirse a reserva when the match is full', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        missingPlayers: 0,
        status: MatchStatus.full,
      ),
      currentUserId: 20,
    );

    expect(find.text('Unirse a reserva'), findsOneWidget);
    expect(find.text('Partido lleno'), findsNothing);
  });

  testWidgets('joining a full match sends the player to reserve', (tester) async {
    const currentUserId = 20;
    final api = _FakeMatchApiService(
      buildMatch(
        organizerId: 10,
        missingPlayers: 0,
        status: MatchStatus.full,
      ),
    );

    await pumpDetail(
      tester,
      match: api.match,
      currentUserId: currentUserId,
      api: api,
    );

    await tester.tap(find.text('Unirse a reserva'));
    await tester.pumpAndSettle();
    expect(find.text('El partido está lleno. ¿Quieres entrar a la lista de reserva?'), findsOneWidget);
    await tester.tap(find.text('Confirmar'));
    await tester.pumpAndSettle();

    expect(api.joinCalled, isTrue);
    expect(find.text('Salir de reserva'), findsOneWidget);
    expect(find.text('Quedaste en la lista de reserva.'), findsOneWidget);
  });

  testWidgets('hides leave and delete after the match has ended', (tester) async {
    await pumpDetail(
      tester,
      match: buildMatch(
        organizerId: 10,
        startTime: DateTime.now().subtract(const Duration(hours: 3)),
        endTime: DateTime.now().subtract(const Duration(hours: 1)),
        players: const [
          MatchPlayerModel(id: 1, userId: 20, quantitySlots: 1),
        ],
      ),
      currentUserId: 20,
    );

    expect(find.text('Salir del partido'), findsNothing);
    expect(find.text('Eliminar'), findsNothing);
    expect(find.text('El partido ya concluyó'), findsOneWidget);
    expect(find.text('Terminar partido'), findsNothing);
  });

  testWidgets('organizer finishes a concluded match and rates players', (tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeMatchApiService(
      buildMatch(
        organizerId: 10,
        startTime: DateTime.now().subtract(const Duration(hours: 3)),
        endTime: DateTime.now().subtract(const Duration(hours: 1)),
        players: const [
          MatchPlayerModel(
            id: 1,
            userId: 30,
            quantitySlots: 1,
            user: UserModel(
              id: 30,
              name: 'Anita Rojas',
              email: 'anita@example.com',
              nickname: 'Anita',
            ),
          ),
        ],
      ),
    );

    await pumpDetail(
      tester,
      match: api.match,
      currentUserId: 10,
      api: api,
    );

    expect(find.text('Salir del partido'), findsNothing);
    expect(find.text('Eliminar'), findsNothing);
    expect(find.text('Terminar partido'), findsOneWidget);

    await tester.tap(find.text('Terminar partido'));
    await tester.pumpAndSettle();
    expect(
      find.text('¿Confirmas que este partido ya concluyó? Después no se podrá salir ni eliminar.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Confirmar'));
    await tester.pumpAndSettle();

    expect(find.text('Calificar jugadores'), findsOneWidget);
    expect(find.text('Anita'), findsWidgets);
    expect(find.text('No vino a jugar'), findsOneWidget);
    expect(find.text('Jugó mal'), findsNothing);

    await tester.tap(find.byTooltip('2 estrellas'));
    await tester.pumpAndSettle();
    expect(find.text('Jugó mal'), findsOneWidget);
    expect(find.text('Mal comportamiento'), findsOneWidget);
    expect(find.text('Buen matador'), findsNothing);

    await tester.tap(find.text('Jugó mal'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('5 estrellas'));
    await tester.pumpAndSettle();
    expect(find.text('Buen matador'), findsOneWidget);
    expect(find.text('Buen defensor'), findsOneWidget);
    expect(find.text('Jugó mal'), findsNothing);

    await tester.tap(find.text('Buen matador'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Terminar partido').last);
    await tester.pumpAndSettle();

    expect(api.finishCalled, isTrue);
    expect(api.finishedRatings, isNotEmpty);
    expect(api.finishedRatings.first['stars'], 5);
    expect(api.finishedRatings.first['tag_ids'], [1]);
    expect(find.text('Partido terminado'), findsOneWidget);
  });
}

class _FakeMatchApiService extends MatchApiService {
  _FakeMatchApiService(this.match) : super(baseUrl: 'http://test');

  MatchModel match;
  bool joinCalled = false;
  bool leaveCalled = false;
  bool deleteCalled = false;
  bool finishCalled = false;
  List<Map<String, dynamic>> finishedRatings = const [];

  @override
  Future<MatchModel> getMatch(int matchId) async => match;

  @override
  Future<MatchModel> joinMatch(int matchId, {int quantitySlots = 1}) async {
    joinCalled = true;
    final toReserve = match.status == MatchStatus.full || match.missingPlayers < quantitySlots;
    match = MatchModel(
      id: match.id,
      organizerId: match.organizerId,
      sportId: match.sportId,
      levelId: match.levelId,
      courtId: match.courtId,
      gender: match.gender,
      sport: match.sport,
      level: match.level,
      court: match.court,
      organizer: match.organizer,
      players: [
        ...?match.players,
        MatchPlayerModel(
          id: 99,
          userId: 20,
          quantitySlots: quantitySlots,
          status: toReserve ? MatchPlayerStatus.reserved : MatchPlayerStatus.pending,
        ),
      ],
      scheduledAt: match.scheduledAt,
      startTime: match.startTime,
      endTime: match.endTime,
      totalPlayers: match.totalPlayers,
      missingPlayers: toReserve ? match.missingPlayers : match.missingPlayers - quantitySlots,
      maxPlayers: match.maxPlayers,
      status: match.status,
    );
    return match;
  }

  @override
  Future<MatchModel> leaveMatch(int matchId) async {
    leaveCalled = true;
    return match;
  }

  @override
  Future<void> deleteMatch(int matchId) async {
    deleteCalled = true;
  }

  @override
  Future<List<RatingTagModel>> listRatingTags(int matchId) async {
    return const [
      RatingTagModel(
        id: 10,
        sportId: 1,
        key: 'jugo_mal',
        label: 'Jugó mal',
        polarity: 'negative',
        marksAbsence: false,
      ),
      RatingTagModel(
        id: 11,
        sportId: 1,
        key: 'mal_comportamiento',
        label: 'Mal comportamiento',
        polarity: 'negative',
        marksAbsence: false,
      ),
      RatingTagModel(
        id: 12,
        sportId: 1,
        key: 'no_vino',
        label: 'No vino a jugar',
        polarity: 'negative',
        marksAbsence: true,
      ),
      RatingTagModel(
        id: 1,
        sportId: 1,
        key: 'buen_matador',
        label: 'Buen matador',
        polarity: 'positive',
        marksAbsence: false,
      ),
      RatingTagModel(
        id: 2,
        sportId: 1,
        key: 'buen_defensor',
        label: 'Buen defensor',
        polarity: 'positive',
        marksAbsence: false,
      ),
    ];
  }

  @override
  Future<MatchModel> finishMatch(
    int matchId, {
    List<Map<String, dynamic>> ratings = const [],
  }) async {
    finishCalled = true;
    finishedRatings = ratings;
    match = MatchModel(
      id: match.id,
      organizerId: match.organizerId,
      sportId: match.sportId,
      levelId: match.levelId,
      courtId: match.courtId,
      gender: match.gender,
      sport: match.sport,
      level: match.level,
      court: match.court,
      organizer: match.organizer,
      players: match.players,
      scheduledAt: match.scheduledAt,
      startTime: match.startTime,
      endTime: match.endTime,
      totalPlayers: match.totalPlayers,
      missingPlayers: match.missingPlayers,
      maxPlayers: match.maxPlayers,
      status: MatchStatus.finished,
    );
    return match;
  }
}
