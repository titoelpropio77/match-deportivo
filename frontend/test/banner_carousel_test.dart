import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/banner_model.dart';
import 'package:frontend/screens/home/widgets/banner_carousel.dart';

void main() {
  const banners = [
    BannerModel(
      id: 1,
      title: 'Reserva tu cancha',
      buttonLabel: 'Reservar ahora',
      link: BannerLink(type: BannerLinkType.reserveCourts),
    ),
    BannerModel(
      id: 2,
      title: 'Copa Wally Sur',
      subtitle: 'Sigue la tabla de posiciones',
      buttonLabel: 'Ver torneo',
      link: BannerLink(type: BannerLinkType.tournament, id: 7),
    ),
    BannerModel(id: 3, title: 'Solo informativo', link: BannerLink(type: BannerLinkType.none)),
  ];

  Future<void> pump(WidgetTester tester, ValueChanged<BannerModel> onPressed) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: BannerCarousel(
              banners: banners,
              interval: const Duration(seconds: 3),
              onBannerPressed: onPressed,
            ),
          ),
        ),
      ),
    );
  }

  testWidgets('advances on its own and loops back to the first banner', (tester) async {
    await pump(tester, (_) {});
    expect(find.text('Reserva tu cancha'), findsOneWidget);

    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('Copa Wally Sur'), findsOneWidget);

    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('Reserva tu cancha'), findsOneWidget);
  });

  testWidgets('shows a dot per banner', (tester) async {
    await pump(tester, (_) {});

    expect(find.byType(AnimatedContainer), findsNWidgets(3));
  });

  testWidgets('tapping the button reports the banner with its link', (tester) async {
    BannerModel? opened;
    await pump(tester, (banner) => opened = banner);

    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ver torneo'));

    expect(opened?.link.type, BannerLinkType.tournament);
    expect(opened?.link.id, 7);
  });

  testWidgets('banners without link have no button and ignore taps', (tester) async {
    var taps = 0;
    await pump(tester, (_) => taps++);

    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(PageView), const Offset(-500, 0));
    await tester.pumpAndSettle();
    expect(find.text('Solo informativo'), findsOneWidget);

    await tester.tap(find.text('Solo informativo'));
    expect(taps, 0);
  });

  test('parses the API payload, colors and unknown link types', () {
    final banner = BannerModel.fromJson({
      'id': 5,
      'title': 'Nuevo',
      'subtitle': null,
      'button_label': 'Ir',
      'image_url': null,
      'background_color': '#16A34A',
      'link': {'type': 'court', 'id': 3, 'url': null},
    });
    expect(banner.link.type, BannerLinkType.court);
    expect(banner.link.id, 3);
    expect(banner.backgroundColor, const Color(0xFF16A34A));

    final future = BannerModel.fromJson({
      'id': 6,
      'title': 'Tipo nuevo',
      'background_color': 'rojo',
      'link': {'type': 'rankings'},
    });
    expect(future.link.type, BannerLinkType.none);
    expect(future.backgroundColor, const Color(0xFF4F46E5));
  });
}
