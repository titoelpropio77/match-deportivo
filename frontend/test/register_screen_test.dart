import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/models/sport_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/register_screen.dart';
import 'package:frontend/services/auth_service.dart';
import 'package:frontend/services/sport_api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('shows the gallery photo picker on the register screen', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: RegisterScreen(
          authService: AuthService(baseUrl: 'http://test'),
          tokenStorage: TokenStorage(),
          onAuthenticated: (_, __) {},
        ),
      ),
    );

    expect(find.text('Elegir de la galería'), findsOneWidget);
    expect(find.byIcon(Icons.photo_library_outlined), findsWidgets);
    expect(find.text('Nombre'), findsOneWidget);
    expect(find.text('Género'), findsOneWidget);
  });

  testWidgets('shows hombre and mujer gender options', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: RegisterScreen(
          authService: AuthService(baseUrl: 'http://test'),
          tokenStorage: TokenStorage(),
          onAuthenticated: (_, __) {},
        ),
      ),
    );

    await tester.tap(find.text('Género'));
    await tester.pumpAndSettle();

    expect(find.text('Hombre'), findsOneWidget);
    expect(find.text('Mujer'), findsOneWidget);
  });

  testWidgets('"Mis deportes favoritos" lists the sports and sends the chosen ones', (tester) async {
    tester.view.physicalSize = const Size(1080, 3200);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final auth = _FakeAuthService();
    UserModel? authenticated;
    await tester.pumpWidget(
      MaterialApp(
        home: RegisterScreen(
          authService: auth,
          tokenStorage: TokenStorage(),
          sportApiService: _FakeSportApi(),
          onAuthenticated: (user, _) => authenticated = user,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mis deportes favoritos'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Fútbol 5'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Wally'), findsOneWidget);
    expect(find.widgetWithText(FilterChip, 'Pádel'), findsOneWidget);

    await tester.ensureVisible(find.widgetWithText(FilterChip, 'Wally'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilterChip, 'Wally'));
    await tester.pump();
    await tester.tap(find.widgetWithText(FilterChip, 'Fútbol 5'));
    await tester.pumpAndSettle();
    expect(find.text('2 elegidos'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextFormField, 'Nombre'), 'Ana Rojas');
    await tester.enterText(find.widgetWithText(TextFormField, 'Email'), 'ana@test.com');
    await tester.tap(find.text('Género'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mujer').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'Contraseña'), 'secret123');
    await tester.enterText(find.widgetWithText(TextFormField, 'Confirmar contraseña'), 'secret123');
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Crear cuenta'));
    await tester.tap(find.widgetWithText(FilledButton, 'Crear cuenta'));
    await tester.pumpAndSettle();

    expect(auth.favoriteSportIds, unorderedEquals([3, 1]));
    expect(authenticated?.name, 'Ana Rojas');
  });
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'futbol_5', name: 'Fútbol 5'),
        SportModel(id: 2, key: 'padel', name: 'Pádel'),
        SportModel(id: 3, key: 'wally', name: 'Wally'),
      ];
}

class _FakeAuthService extends AuthService {
  _FakeAuthService() : super(baseUrl: 'http://test');

  List<int>? favoriteSportIds;

  @override
  Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    required String gender,
    DateTime? birthDate,
    List<int> favoriteSportIds = const [],
    String? photoPath,
    List<int>? photoBytes,
    String? photoFilename,
  }) async {
    this.favoriteSportIds = favoriteSportIds;
    return AuthResult(user: UserModel(id: 9, name: name, email: email), token: 'token');
  }
}
