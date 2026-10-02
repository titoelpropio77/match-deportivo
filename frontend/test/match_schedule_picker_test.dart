import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/screens/match_schedule_picker.dart';

void main() {
  // Thursday 1 October 2026, 18:10.
  final now = DateTime(2026, 10, 1, 18, 10);

  Future<List<(DateTime?, DateTime?)>> pump(
    WidgetTester tester, {
    String? opening = '08:00:00',
    String? closing = '22:00:00',
  }) async {
    tester.view.physicalSize = const Size(1080, 3000);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);

    final changes = <(DateTime?, DateTime?)>[];
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: StatefulBuilder(
              builder: (context, setState) => MatchSchedulePicker(
                start: changes.isEmpty ? null : changes.last.$1,
                end: changes.isEmpty ? null : changes.last.$2,
                openingTime: opening,
                closingTime: closing,
                now: now,
                onChanged: (start, end) => setState(() => changes.add((start, end))),
              ),
            ),
          ),
        ),
      ),
    );
    return changes;
  }

  testWidgets('day, then start and end in 30-minute steps', (tester) async {
    final changes = await pump(tester);

    expect(find.text('Hoy'), findsOneWidget);
    expect(find.text('Mañana'), findsOneWidget);
    expect(find.text('El centro atiende de 08:00 a 22:00.'), findsOneWidget);
    // Times appear only after picking a day.
    expect(find.text('19:00'), findsNothing);

    await tester.tap(find.text('Mañana'));
    await tester.pumpAndSettle();

    expect(find.text('08:00'), findsOneWidget);
    expect(find.text('08:30'), findsOneWidget);
    expect(find.text('21:30'), findsOneWidget);
    expect(find.text('22:00'), findsNothing);
    expect(find.text('Por la mañana'), findsOneWidget);
    expect(find.text('Por la tarde'), findsOneWidget);
    expect(find.text('Por la noche'), findsOneWidget);

    await tester.tap(find.text('19:00'));
    await tester.pumpAndSettle();

    // One hour by default; end options go up to closing time.
    expect(changes.last, (DateTime(2026, 10, 2, 19), DateTime(2026, 10, 2, 20)));
    expect(find.text('Viernes 2 de octubre'), findsOneWidget);
    expect(find.text('19:00 – 20:00 · 1 h'), findsOneWidget);
    expect(find.text('19:30 · 30 min'), findsOneWidget);
    expect(find.text('22:00 · 3 h'), findsOneWidget);
    expect(find.text('22:30 · 3 h 30'), findsNothing);

    await tester.tap(find.text('20:30 · 1 h 30'));
    await tester.pumpAndSettle();

    expect(changes.last, (DateTime(2026, 10, 2, 19), DateTime(2026, 10, 2, 20, 30)));
    expect(find.text('19:00 – 20:30 · 1 h 30'), findsOneWidget);

    // A later start keeps the chosen length.
    await tester.tap(find.text('20:00'));
    await tester.pumpAndSettle();
    expect(changes.last, (DateTime(2026, 10, 2, 20), DateTime(2026, 10, 2, 21, 30)));
  });

  testWidgets('today only offers times that have not started yet', (tester) async {
    await pump(tester);

    await tester.tap(find.text('Hoy'));
    await tester.pumpAndSettle();

    expect(find.text('18:00'), findsNothing);
    expect(find.text('18:30'), findsOneWidget);
    expect(find.text('Por la mañana'), findsNothing);
    expect(find.text('Por la noche'), findsOneWidget);
  });

  testWidgets('without venue hours it offers 06:00 to midnight', (tester) async {
    await pump(tester, opening: null, closing: null);

    await tester.tap(find.text('Mañana'));
    await tester.pumpAndSettle();

    expect(find.text('05:30'), findsNothing);
    expect(find.text('06:00'), findsOneWidget);
    expect(find.text('23:30'), findsOneWidget);
    expect(find.textContaining('El centro atiende'), findsNothing);
  });
}
