import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/featured_court_model.dart';
import 'package:frontend/screens/home/widgets/featured_courts_section.dart';

void main() {
  const courts = [
    FeaturedCourtModel(
      id: 1,
      name: 'Complejo Wally Sur con un nombre muy largo',
      address: 'Av. Santos Dumont 3er anillo',
      city: 'Santa Cruz de la Sierra',
      rating: 4.8,
      reviewsCount: 32,
      minPrice: 60,
      sports: ['Pádel', 'Wally', 'Frontón'],
      highlight: FeaturedHighlight.topRated,
    ),
    FeaturedCourtModel(
      id: 2,
      name: 'Arena Norte',
      address: 'Zona Norte',
      city: 'Santa Cruz de la Sierra',
      isNew: true,
      highlight: FeaturedHighlight.isNew,
    ),
  ];

  Future<void> pump(WidgetTester tester, Widget section, {double textScale = 1}) async {
    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(size: const Size(400, 800), textScaler: TextScaler.linear(textScale)),
          child: Scaffold(body: Padding(padding: const EdgeInsets.all(16), child: section)),
        ),
      ),
    );
    await tester.pump();
  }

  FeaturedCourtsSection section({
    List<FeaturedCourtModel> list = courts,
    bool loading = false,
    String? error,
    ValueChanged<FeaturedCourtModel>? onPressed,
  }) {
    return FeaturedCourtsSection(
      courts: list,
      loading: loading,
      error: error,
      onRetry: () {},
      onCourtPressed: onPressed ?? (_) {},
    );
  }

  testWidgets('shows real data with badges and no overflow', (tester) async {
    await pump(tester, section());

    expect(find.text('Complejos destacados'), findsOneWidget);
    expect(find.text('Mejor calificado'), findsOneWidget);
    expect(find.text('Nuevo'), findsOneWidget);
    expect(find.text('Desde Bs. 60/hora'), findsOneWidget);
    expect(find.text('Precio a consultar'), findsOneWidget);
    expect(find.text('Sin reseñas'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('does not overflow with larger system text', (tester) async {
    await pump(tester, section(), textScale: 1.15);

    expect(tester.takeException(), isNull);
  });

  testWidgets('tapping "Ver complejo" reports the center', (tester) async {
    FeaturedCourtModel? opened;
    await pump(tester, section(onPressed: (court) => opened = court));

    await tester.tap(find.text('Ver complejo').first);
    expect(opened?.id, 1);
  });

  testWidgets('shows loading, error and empty states', (tester) async {
    await pump(tester, section(loading: true, list: const []));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    await pump(tester, section(error: 'No pudimos cargar los complejos destacados.', list: const []));
    expect(find.text('Reintentar'), findsOneWidget);

    await pump(tester, section(list: const []));
    expect(find.text('Todavía no hay complejos deportivos registrados.'), findsOneWidget);
  });
}
