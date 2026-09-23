import 'package:flutter/material.dart';

import 'config/app_config.dart';
import 'models/user_model.dart';
import 'screens/home/home_shell_screen.dart';
import 'screens/login_screen.dart';
import 'services/auth_service.dart';
import 'services/match_api_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppConfig.load();
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  // This widget is the root of your application.
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Match Deportivo',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
      ),
      home: const _AuthGate(),
    );
  }
}

enum _SessionStatus { checking, authenticated, unauthenticated }

class _AuthGate extends StatefulWidget {
  const _AuthGate();

  @override
  State<_AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<_AuthGate> {
  final _tokenStorage = TokenStorage();
  late final _authService = AuthService(baseUrl: AppConfig.backendUrl);

  _SessionStatus _status = _SessionStatus.checking;
  String? _token;
  UserModel? _user;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  @override
  void dispose() {
    _authService.dispose();
    super.dispose();
  }

  /// Validates any stored token against the backend before deciding where to navigate.
  Future<void> _restoreSession() async {
    final token = await _tokenStorage.read();
    if (token == null || token.isEmpty) {
      if (!mounted) return;
      setState(() => _status = _SessionStatus.unauthenticated);
      return;
    }

    try {
      final user = await _authService.me(token);
      if (!mounted) return;
      setState(() {
        _token = token;
        _user = user;
        _status = _SessionStatus.authenticated;
      });
    } catch (_) {
      await _tokenStorage.clear();
      if (!mounted) return;
      setState(() => _status = _SessionStatus.unauthenticated);
    }
  }

  void _handleAuthenticated(UserModel user, String token) {
    setState(() {
      _token = token;
      _user = user;
      _status = _SessionStatus.authenticated;
    });
  }

  Future<void> _handleLogout() async {
    final token = _token;
    if (token != null) {
      try {
        await _authService.logout(token);
      } catch (_) {
        // Ignore network errors on logout; the local session is cleared regardless.
      }
    }
    await _tokenStorage.clear();
    if (!mounted) return;
    setState(() {
      _token = null;
      _user = null;
      _status = _SessionStatus.unauthenticated;
    });
  }

  @override
  Widget build(BuildContext context) {
    switch (_status) {
      case _SessionStatus.checking:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case _SessionStatus.authenticated:
        return HomeShellScreen(
          user: _user!,
          matchApiService: MatchApiService(
            baseUrl: AppConfig.backendUrl,
            token: _token,
          ),
          onLogout: _handleLogout,
        );
      case _SessionStatus.unauthenticated:
        return LoginScreen(
          authService: _authService,
          tokenStorage: _tokenStorage,
          onAuthenticated: _handleAuthenticated,
        );
    }
  }
}


