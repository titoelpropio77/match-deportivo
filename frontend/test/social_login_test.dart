import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/models/sport_model.dart';
import 'package:frontend/models/user_model.dart';
import 'package:frontend/screens/complete_profile_screen.dart';
import 'package:frontend/screens/login_screen.dart';
import 'package:frontend/screens/register_screen.dart';
import 'package:frontend/services/auth_service.dart';
import 'package:frontend/services/social_sign_in_service.dart';
import 'package:frontend/services/sport_api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  Future<void> pumpLogin(
    WidgetTester tester, {
    required _FakeAuthService auth,
    required _FakeSocialSignIn social,
    required void Function(UserModel, String) onAuthenticated,
  }) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          authService: auth,
          tokenStorage: TokenStorage(),
          socialSignIn: social,
          onAuthenticated: onAuthenticated,
        ),
      ),
    );
  }

  testWidgets('login and register offer Google and Facebook', (tester) async {
    await pumpLogin(
      tester,
      auth: _FakeAuthService(),
      social: _FakeSocialSignIn(),
      onAuthenticated: (_, _) {},
    );

    expect(find.text('o continúa con'), findsOneWidget);
    expect(find.text('Continuar con Google'), findsOneWidget);
    expect(find.text('Continuar con Facebook'), findsOneWidget);

    await tester.pumpWidget(
      MaterialApp(
        home: RegisterScreen(
          authService: _FakeAuthService(),
          tokenStorage: TokenStorage(),
          sportApiService: _FakeSportApi(),
          socialSignIn: _FakeSocialSignIn(),
          onAuthenticated: (_, _) {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Continuar con Google'), findsOneWidget);
    expect(find.text('Continuar con Facebook'), findsOneWidget);
  });

  testWidgets('Google sends its token to the backend and saves the session', (tester) async {
    final auth = _FakeAuthService();
    final social = _FakeSocialSignIn(token: 'google-token');
    UserModel? authenticated;
    await pumpLogin(
      tester,
      auth: auth,
      social: social,
      onAuthenticated: (user, _) => authenticated = user,
    );

    await tester.tap(find.text('Continuar con Google'));
    await tester.pumpAndSettle();

    expect(social.requested, SocialProvider.google);
    expect(auth.provider, SocialProvider.google);
    expect(auth.accessToken, 'google-token');
    expect(authenticated?.profileCompleted, isFalse);
    expect(await TokenStorage().read(), 'session-token');
  });

  testWidgets('cancelling Facebook does nothing and an error is shown in Spanish', (tester) async {
    final auth = _FakeAuthService();
    final social = _FakeSocialSignIn();
    var calls = 0;
    await pumpLogin(tester, auth: auth, social: social, onAuthenticated: (_, _) => calls++);

    await tester.tap(find.text('Continuar con Facebook'));
    await tester.pumpAndSettle();

    expect(social.requested, SocialProvider.facebook);
    expect(auth.provider, isNull);
    expect(calls, 0);

    social.token = 'fb-token';
    auth.error = const AuthApiException('Invalid', 422, fieldErrors: {
      'access_token': ['Tu cuenta de Facebook no comparte un email. Regístrate con tu email y contraseña.'],
    });
    await tester.tap(find.text('Continuar con Facebook'));
    await tester.pumpAndSettle();

    expect(find.textContaining('no comparte un email'), findsOneWidget);
    expect(calls, 0);
    expect(social.signedOut, isTrue);
  });

  testWidgets('"Completa tu perfil" requires the gender and sends the data', (tester) async {
    tester.view.physicalSize = const Size(1080, 3600);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final auth = _FakeAuthService();
    UserModel? completed;
    await tester.pumpWidget(
      MaterialApp(
        home: CompleteProfileScreen(
          user: const UserModel(
            id: 7,
            name: 'Ana Rojas',
            email: 'ana@gmail.com',
            profileCompleted: false,
          ),
          token: 'session-token',
          authService: auth,
          sportApiService: _FakeSportApi(),
          onCompleted: (user) => completed = user,
          onLogout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Completa tu perfil'), findsOneWidget);
    expect(find.text('¡Bienvenido, Ana!'), findsOneWidget);
    expect(find.widgetWithText(TextFormField, 'Ana Rojas'), findsOneWidget);

    await tester.tap(find.text('Continuar'));
    await tester.pumpAndSettle();
    expect(find.text('El género es obligatorio.'), findsOneWidget);
    expect(completed, isNull);

    await tester.enterText(find.widgetWithText(TextFormField, 'Apodo (opcional)'), 'Anita');
    await tester.enterText(find.widgetWithText(TextFormField, 'Teléfono (opcional)'), '70000000');
    await tester.tap(find.text('Género'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mujer').last);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilterChip, 'Wally'));
    await tester.pump();

    await tester.tap(find.text('Continuar'));
    await tester.pumpAndSettle();

    expect(auth.completedWith, {
      'token': 'session-token',
      'name': 'Ana Rojas',
      'gender': 'female',
      'nickname': 'Anita',
      'phone': '70000000',
      'sports': [3],
    });
    expect(completed?.profileCompleted, isTrue);
  });
}

class _FakeSocialSignIn extends SocialSignInService {
  _FakeSocialSignIn({this.token});

  String? token;
  SocialProvider? requested;
  bool signedOut = false;

  @override
  Future<String?> accessToken(SocialProvider provider) async {
    requested = provider;
    return token;
  }

  @override
  Future<void> signOut() async => signedOut = true;
}

class _FakeSportApi extends SportApiService {
  _FakeSportApi() : super(baseUrl: 'http://test');

  @override
  Future<List<SportModel>> list() async => const [
        SportModel(id: 1, key: 'futbol_5', name: 'Fútbol 5'),
        SportModel(id: 3, key: 'wally', name: 'Wally'),
      ];
}

class _FakeAuthService extends AuthService {
  _FakeAuthService() : super(baseUrl: 'http://test');

  SocialProvider? provider;
  String? accessToken;
  AuthApiException? error;
  Map<String, Object?>? completedWith;

  @override
  Future<AuthResult> socialLogin({
    required SocialProvider provider,
    required String accessToken,
  }) async {
    if (error != null) throw error!;
    this.provider = provider;
    this.accessToken = accessToken;
    return const AuthResult(
      user: UserModel(id: 7, name: 'Ana Rojas', email: 'ana@gmail.com', profileCompleted: false),
      token: 'session-token',
    );
  }

  @override
  Future<UserModel> completeProfile({
    required String token,
    required String name,
    required String gender,
    String? nickname,
    String? phone,
    String? preferredPosition,
    DateTime? birthDate,
    List<int> favoriteSportIds = const [],
  }) async {
    completedWith = {
      'token': token,
      'name': name,
      'gender': gender,
      'nickname': nickname,
      'phone': phone,
      'sports': favoriteSportIds,
    };
    return UserModel(id: 7, name: name, email: 'ana@gmail.com', gender: gender);
  }
}
