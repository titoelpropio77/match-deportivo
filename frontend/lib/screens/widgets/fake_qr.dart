import 'package:flutter/material.dart';

/// Draws a QR-looking grid derived from [data]. Placeholder until the bank QR is integrated.
class FakeQrPainter extends CustomPainter {
  FakeQrPainter(this.data);

  final String data;

  static const _cells = 25;

  @override
  void paint(Canvas canvas, Size size) {
    final cell = size.width / _cells;
    final paint = Paint()..color = Colors.black;
    var seed = data.codeUnits.fold<int>(17, (hash, unit) => (hash * 31 + unit) & 0x7fffffff);

    bool inFinder(int x, int y) {
      bool near(int ox, int oy) => x >= ox && x < ox + 8 && y >= oy && y < oy + 8;
      return near(0, 0) || near(_cells - 8, 0) || near(0, _cells - 8);
    }

    for (var y = 0; y < _cells; y++) {
      for (var x = 0; x < _cells; x++) {
        if (inFinder(x, y)) continue;
        seed = (seed * 1103515245 + 12345) & 0x7fffffff;
        if (seed % 2 == 0) {
          canvas.drawRect(Rect.fromLTWH(x * cell, y * cell, cell, cell), paint);
        }
      }
    }

    void finder(int ox, int oy) {
      final outer = Rect.fromLTWH(ox * cell, oy * cell, cell * 7, cell * 7);
      canvas.drawRect(outer, paint);
      canvas.drawRect(outer.deflate(cell), Paint()..color = Colors.white);
      canvas.drawRect(outer.deflate(cell * 2), paint);
    }

    finder(0, 0);
    finder(_cells - 7, 0);
    finder(0, _cells - 7);
  }

  @override
  bool shouldRepaint(FakeQrPainter oldDelegate) => oldDelegate.data != data;
}
