import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/screens/register_screen.dart';
import 'package:frontend/services/auth_service.dart';

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
}
