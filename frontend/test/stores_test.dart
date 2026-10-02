import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/store_model.dart';
import 'package:frontend/screens/stores/my_orders_screen.dart';
import 'package:frontend/screens/stores/store_cart.dart';
import 'package:frontend/screens/stores/store_detail_screen.dart';
import 'package:frontend/screens/stores/stores_screen.dart';
import 'package:frontend/services/store_api_service.dart';

const balls = ProductCategoryModel(id: 1, key: 'balls', name: 'Balones y pelotas', storesCount: 1);
const drinks = ProductCategoryModel(id: 8, key: 'drinks', name: 'Bebidas', storesCount: 1);
const footwear = ProductCategoryModel(id: 3, key: 'footwear', name: 'Calzado', storesCount: 0);

const shop = StoreModel(
  id: 1,
  name: 'Wally Shop',
  description: 'Todo para tu partido.',
  categories: [balls, drinks],
  productsCount: 3,
  offersCount: 1,
  venue: StoreVenueModel(id: 10, name: 'Complejo Wally Sur', address: 'Santa Cruz', openingTime: '08:00', closingTime: '23:00'),
);

const ball = ProductModel(
  id: 1,
  storeId: 1,
  name: 'Pelota de wally',
  price: 120,
  finalPrice: 108,
  discountPercent: 10,
  available: 2,
  category: balls,
);
const drink = ProductModel(id: 2, storeId: 1, name: 'Isotónica', price: 12, finalPrice: 12, available: 10, category: drinks);
const energy = ProductModel(id: 3, storeId: 1, name: 'Energética', price: 15, finalPrice: 15, available: 0, category: drinks);

void main() {
  test('the cart never holds more than the available units', () {
    final cart = StoreCart();
    cart.add(ball);
    cart.add(ball);
    cart.add(ball);
    cart.set(drink, 3);
    expect(cart.quantityOf(ball.id), 2);
    expect(cart.canAdd(ball), isFalse);
    expect(cart.units, 5);
    expect(cart.total, 252);
    expect(cart.savings, 24);

    // Fresh data with less stock trims the cart; products no longer sold leave it.
    cart.refresh([const ProductModel(id: 1, storeId: 1, name: 'Pelota de wally', price: 120, finalPrice: 108, available: 1)]);
    expect(cart.quantityOf(ball.id), 1);
    expect(cart.quantityOf(drink.id), 0);
  });

  testWidgets('stores are listed and filtered by the categories some store sells', (tester) async {
    final api = _FakeStoreApi();
    await tester.pumpWidget(MaterialApp(home: StoresScreen(storeApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Wally Shop'), findsOneWidget);
    expect(find.text('1 oferta'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Todas'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Bebidas'), findsOneWidget);
    // No store sells it.
    expect(find.widgetWithText(ChoiceChip, 'Calzado'), findsNothing);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Bebidas'));
    await tester.pumpAndSettle();
    expect(api.lastCategoryId, drinks.id);
  });

  testWidgets('products go to the cart and are paid with the simulated QR', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final api = _FakeStoreApi();

    await tester.pumpWidget(MaterialApp(home: StoreDetailScreen(store: shop, storeApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Pelota de wally'), findsOneWidget);
    expect(find.text('-10%'), findsOneWidget);
    expect(find.text('AGOTADO'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Ofertas'), findsOneWidget);

    // Two "add" buttons: the sold out product has none.
    final addButtons = find.byTooltip('Agregar');
    expect(addButtons, findsNWidgets(2));
    await tester.tap(addButtons.first);
    await tester.pump();
    await tester.tap(addButtons.first);
    await tester.pump();
    expect(find.text('2 productos en tu carrito'), findsOneWidget);
    expect(find.text('Bs 216'), findsOneWidget);

    await tester.tap(find.text('Ver carrito'));
    await tester.pumpAndSettle();
    expect(find.text('Tu carrito'), findsOneWidget);
    expect(find.text('Retiro en tienda'), findsOneWidget);
    expect(find.text('− Bs 24'), findsOneWidget);

    await tester.tap(find.textContaining('Pagar con QR'));
    await tester.pumpAndSettle();
    expect(api.placed, {ball.id: 2});
    expect(find.text('Referencia: TABC1234'), findsOneWidget);

    await tester.ensureVisible(find.text('Simular pago'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simular pago'));
    await tester.pumpAndSettle();
    expect(find.text('Muestra este código en la tienda'), findsOneWidget);
    expect(find.text('TABC1234'), findsOneWidget);

    await tester.ensureVisible(find.text('Listo'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Listo'));
    await tester.pumpAndSettle();
    // Back at the store with an empty cart.
    expect(find.text('Ver carrito'), findsNothing);
  });

  testWidgets('my orders show the pickup status and resume a pending payment', (tester) async {
    final api = _FakeStoreApi();
    await tester.pumpWidget(MaterialApp(home: MyOrdersScreen(storeApiService: api)));
    await tester.pumpAndSettle();

    expect(find.text('Pago pendiente'), findsOneWidget);
    expect(find.text('Pagado · listo para retirar'), findsOneWidget);
    expect(find.text('Anulado por la tienda'), findsOneWidget);
    expect(find.text('Motivo: Producto dañado'), findsOneWidget);
    expect(find.widgetWithText(TextButton, 'Pagar'), findsOneWidget);

    await tester.tap(find.widgetWithText(TextButton, 'Pagar'));
    await tester.pumpAndSettle();
    expect(find.text('Pagar pedido'), findsOneWidget);
    expect(find.text('Referencia: TPEND001'), findsOneWidget);
  });
}

class _FakeStoreApi extends StoreApiService {
  _FakeStoreApi() : super(baseUrl: 'http://test');

  int? lastCategoryId;
  Map<int, int>? placed;

  StoreOrderModel _order(String code, String status, {DateTime? deliveredAt, String? reason}) {
    return StoreOrderModel(
      id: code.hashCode,
      code: code,
      status: status,
      subtotal: 240,
      discountAmount: 24,
      total: 216,
      items: const [StoreOrderItemModel(productId: 1, name: 'Pelota de wally', unitPrice: 108, quantity: 2, amount: 216)],
      createdAt: DateTime.now(),
      paymentExpiresAt: DateTime.now().add(const Duration(minutes: 15)),
      deliveredAt: deliveredAt,
      cancelledByVenue: reason != null,
      cancellationReason: reason,
      store: shop,
    );
  }

  @override
  Future<List<ProductCategoryModel>> categories() async => const [balls, drinks, footwear];

  @override
  Future<List<StoreModel>> stores({int? categoryId, String? search}) async {
    lastCategoryId = categoryId;
    return const [shop];
  }

  @override
  Future<List<ProductModel>> products(int storeId) async => const [energy, ball, drink];

  @override
  Future<StoreOrderModel> placeOrder({required int storeId, required List<CartLine> lines, String? notes}) async {
    placed = {for (final line in lines) line.product.id: line.quantity};
    return _order('TABC1234', 'pending_payment');
  }

  @override
  Future<StoreOrderModel> pay(int orderId) async => _order('TABC1234', 'paid');

  @override
  Future<void> cancel(int orderId) async {}

  @override
  Future<List<StoreOrderModel>> myOrders() async => [
        _order('TPEND001', 'pending_payment'),
        _order('TPAID001', 'paid'),
        _order('TCANC001', 'cancelled', reason: 'Producto dañado'),
      ];
}
