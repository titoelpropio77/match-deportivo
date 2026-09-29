import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/sport_model.dart';
import 'package:frontend/screens/search_teams/widgets/open_match_filters.dart';

void main() {
  const sports = [
    SportModel(id: 1, key: 'football_5', name: 'Fútbol 5'),
    SportModel(id: 2, key: 'basketball', name: 'Baloncesto'),
  ];

  testWidgets('shows date and sport filters', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: OpenMatchFilters(
            sports: sports,
            selectedDate: null,
            selectedSportId: null,
            onDateChanged: _noopDate,
            onSportChanged: _noopSport,
          ),
        ),
      ),
    );

    expect(find.text('Fecha'), findsOneWidget);
    expect(find.text('Deporte'), findsOneWidget);
  });

  testWidgets('shows the selected sport name', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: OpenMatchFilters(
            sports: sports,
            selectedDate: null,
            selectedSportId: 2,
            onDateChanged: _noopDate,
            onSportChanged: _noopSport,
          ),
        ),
      ),
    );

    expect(find.text('Baloncesto'), findsOneWidget);
    expect(find.text('Deporte'), findsNothing);
  });

  testWidgets('opens the sport picker', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: OpenMatchFilters(
            sports: sports,
            selectedDate: null,
            selectedSportId: null,
            onDateChanged: _noopDate,
            onSportChanged: _noopSport,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Deporte'));
    await tester.pumpAndSettle();

    expect(find.text('Tipo de partido'), findsOneWidget);
    expect(find.text('Todos'), findsOneWidget);
    expect(find.text('Fútbol 5'), findsOneWidget);
  });
}

void _noopDate(DateTime? _) {}

void _noopSport(int? _) {}
