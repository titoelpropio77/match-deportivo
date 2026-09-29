import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/screens/create_match_screen.dart';

void main() {
  Future<void> openDialog(WidgetTester tester, ValueChanged<bool?> onResult) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: FilledButton(
              onPressed: () async {
                onResult(await showCreateMatchJoinDialog(context));
              },
              child: const Text('Abrir'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Abrir'));
    await tester.pumpAndSettle();
  }

  testWidgets('shows the three create-match options', (tester) async {
    await openDialog(tester, (_) {});

    expect(find.text('¿Quieres unirte al partido?'), findsOneWidget);
    expect(find.text('Crear y añadirme como jugador'), findsOneWidget);
    expect(find.text('Solo crear el partido'), findsOneWidget);
    expect(find.text('Cancelar'), findsOneWidget);
  });

  testWidgets('joining as player returns true', (tester) async {
    bool? result;
    await openDialog(tester, (value) => result = value);
    await tester.tap(find.text('Crear y añadirme como jugador'));
    await tester.pumpAndSettle();

    expect(result, isTrue);
  });

  testWidgets('creating only returns false', (tester) async {
    bool? result;
    await openDialog(tester, (value) => result = value);
    await tester.tap(find.text('Solo crear el partido'));
    await tester.pumpAndSettle();

    expect(result, isFalse);
  });

  testWidgets('cancel returns null', (tester) async {
    var called = false;
    bool? result = false;
    await openDialog(tester, (value) {
      called = true;
      result = value;
    });
    await tester.tap(find.text('Cancelar'));
    await tester.pumpAndSettle();

    expect(called, isTrue);
    expect(result, isNull);
  });
}
