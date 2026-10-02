import 'package:flutter/foundation.dart';

import '../../models/store_model.dart';

/// Cart of one store, shared by the store, product and checkout screens. Quantities never go
/// above the units the product has available.
class StoreCart extends ChangeNotifier {
  final Map<int, CartLine> _lines = {};

  List<CartLine> get lines => _lines.values.toList();
  bool get isEmpty => _lines.isEmpty;
  int get units => _lines.values.fold(0, (sum, line) => sum + line.quantity);
  double get total => _lines.values.fold(0, (sum, line) => sum + line.amount);
  double get listTotal => _lines.values.fold(0, (sum, line) => sum + line.listAmount);
  double get savings => listTotal - total;

  int quantityOf(int productId) => _lines[productId]?.quantity ?? 0;

  bool canAdd(ProductModel product) => quantityOf(product.id) < product.available;

  void set(ProductModel product, int quantity) {
    final capped = quantity.clamp(0, product.available);
    if (capped == 0) {
      _lines.remove(product.id);
    } else {
      _lines[product.id] = CartLine(product: product, quantity: capped);
    }
    notifyListeners();
  }

  void add(ProductModel product) => set(product, quantityOf(product.id) + 1);

  void remove(ProductModel product) => set(product, quantityOf(product.id) - 1);

  /// Keeps the cart in line with fresh product data (price, stock, products no longer sold).
  void refresh(List<ProductModel> products) {
    final byId = {for (final product in products) product.id: product};
    for (final id in _lines.keys.toList()) {
      final product = byId[id];
      final quantity = _lines[id]!.quantity.clamp(0, product?.available ?? 0);
      if (product == null || quantity == 0) {
        _lines.remove(id);
      } else {
        _lines[id] = CartLine(product: product, quantity: quantity);
      }
    }
    notifyListeners();
  }

  void clear() {
    _lines.clear();
    notifyListeners();
  }
}
