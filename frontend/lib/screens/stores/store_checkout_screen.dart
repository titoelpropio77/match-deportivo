import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../../services/store_api_service.dart';
import '../reserve_court/reserve_courts_screen.dart';
import '../widgets/fake_qr.dart';
import 'store_cart.dart';
import 'store_widgets.dart';

/// Cart review and simulated QR payment of a store purchase. "Pagar con QR" holds the units as
/// pending payment; [order] resumes a pending one from "Mis compras". Pops `true` once paid.
class StoreCheckoutScreen extends StatefulWidget {
  const StoreCheckoutScreen({
    required this.store,
    required this.storeApiService,
    this.cart,
    this.order,
    super.key,
  }) : assert(cart != null || order != null);

  final StoreModel store;
  final StoreCart? cart;
  final StoreOrderModel? order;
  final StoreApiService storeApiService;

  @override
  State<StoreCheckoutScreen> createState() => _StoreCheckoutScreenState();
}

class _StoreCheckoutScreenState extends State<StoreCheckoutScreen> {
  late StoreOrderModel? _order = widget.order;
  final _notes = TextEditingController();
  bool _busy = false;
  Timer? _timer;
  Duration _remaining = Duration.zero;

  bool get _expired => _order?.paymentExpiresAt != null && _remaining <= Duration.zero;

  @override
  void initState() {
    super.initState();
    if (_order != null) _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _generateQr() async {
    final cart = widget.cart!;
    setState(() => _busy = true);
    try {
      final order = await widget.storeApiService.placeOrder(storeId: widget.store.id, lines: cart.lines, notes: _notes.text);
      if (!mounted) return;
      setState(() => _order = order);
      _startCountdown();
    } catch (error) {
      _showError(error, 'No pudimos registrar tu pedido.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _startCountdown() {
    final expiresAt = _order?.paymentExpiresAt;
    if (expiresAt == null) return;
    void tick() {
      final remaining = expiresAt.difference(DateTime.now());
      setState(() => _remaining = remaining.isNegative ? Duration.zero : remaining);
      if (remaining <= Duration.zero) _timer?.cancel();
    }

    tick();
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _simulatePayment() async {
    final order = _order;
    if (order == null) return;
    setState(() => _busy = true);
    try {
      final paid = await widget.storeApiService.pay(order.id);
      if (!mounted) return;
      _timer?.cancel();
      await Navigator.of(context).push<void>(
        MaterialPageRoute(builder: (_) => StoreOrderSuccessScreen(store: widget.store, order: paid)),
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      _showError(error, 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Leaving with an unpaid order releases its units.
  Future<void> _leave() async {
    final order = _order;
    if (order == null || _expired) {
      Navigator.of(context).pop();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cancelar el pedido?'),
        content: const Text('Todavía no pagaste. Si sales, los productos quedarán libres para otros.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Seguir pagando')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Cancelar pedido')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.storeApiService.cancel(order.id);
    } catch (_) {
      // The hold expires on its own after the payment window.
    }
    if (mounted) Navigator.of(context).pop();
  }

  void _showError(Object error, String fallback) {
    if (!mounted) return;
    final message = error is StoreApiException ? error.message : fallback;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final cart = widget.cart;
    return PopScope(
      canPop: order == null || _expired,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(title: Text(order == null ? 'Tu carrito' : 'Pagar pedido')),
        body: order == null && cart != null
            ? ListenableBuilder(listenable: cart, builder: (context, _) => _cartView(cart))
            : _orderView(order!),
      ),
    );
  }

  Widget _cartView(StoreCart cart) {
    final textTheme = Theme.of(context).textTheme;
    if (cart.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.remove_shopping_cart_outlined, size: 48),
            const SizedBox(height: 12),
            const Text('Tu carrito está vacío.'),
            TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Volver a la tienda')),
          ],
        ),
      );
    }
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(widget.store.name, style: textTheme.titleMedium),
        const SizedBox(height: 8),
        for (final line in cart.lines)
          _CartLineTile(
            line: line,
            onMinus: () => cart.remove(line.product),
            onPlus: cart.canAdd(line.product) ? () => cart.add(line.product) : null,
          ),
        const Divider(height: 24),
        if (cart.savings > 0.001) ...[
          _Row(label: 'Subtotal', value: formatBs(cart.listTotal)),
          _Row(label: 'Descuentos', value: '− ${formatBs(cart.savings)}', color: Colors.red.shade700),
        ],
        _Row(label: 'Total', value: formatBs(cart.total), emphasize: true),
        const SizedBox(height: 12),
        _PickupCard(store: widget.store),
        const SizedBox(height: 16),
        TextField(
          controller: _notes,
          maxLength: 500,
          maxLines: 2,
          decoration: const InputDecoration(
            labelText: 'Nota para la tienda (opcional)',
            hintText: 'Ej: talla M, paso a retirar después del partido...',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 8),
        FilledButton.icon(
          onPressed: _busy ? null : _generateQr,
          icon: _busy ? const _ButtonSpinner() : const Icon(Icons.qr_code_2),
          label: Text('Pagar con QR · ${formatBs(cart.total)}'),
        ),
      ],
    );
  }

  Widget _orderView(StoreOrderModel order) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final minutes = _remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _OrderSummary(store: widget.store, order: order),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Text('Escanea el QR para pagar', style: textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(formatBs(order.total), style: textTheme.headlineMedium),
                const SizedBox(height: 12),
                Opacity(
                  opacity: _expired ? 0.2 : 1,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    color: Colors.white,
                    child: SizedBox.square(
                      dimension: 200,
                      child: CustomPaint(painter: FakeQrPainter('${order.code}|${order.total}')),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Text('Referencia: ${order.code}', style: textTheme.bodySmall),
                const SizedBox(height: 4),
                Text(
                  _expired
                      ? 'El QR expiró y los productos fueron liberados.'
                      : 'Tus productos quedan apartados por $minutes:$seconds',
                  textAlign: TextAlign.center,
                  style: textTheme.bodyMedium?.copyWith(color: _expired ? colors.error : null),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: colors.secondaryContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    'Modo prueba: el pago por QR todavía no está conectado al banco. '
                    'Usa "Simular pago" para confirmar el pedido.',
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _busy || _expired ? null : _simulatePayment,
                    icon: _busy ? const _ButtonSpinner() : const Icon(Icons.check_circle_outline),
                    label: const Text('Simular pago'),
                  ),
                ),
                if (!_expired) TextButton(onPressed: _busy ? null : _leave, child: const Text('Cancelar pedido')),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// Paid: the code to show at the store to pick up the order.
class StoreOrderSuccessScreen extends StatelessWidget {
  const StoreOrderSuccessScreen({required this.store, required this.order, super.key});

  final StoreModel store;
  final StoreOrderModel order;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Compra confirmada'), automaticallyImplyLeading: false),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Icon(Icons.shopping_bag, size: 72, color: colors.tertiary),
          const SizedBox(height: 12),
          Text('¡Listo! Tu pedido te espera', textAlign: TextAlign.center, style: textTheme.headlineSmall),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 16),
            decoration: BoxDecoration(
              color: colors.primaryContainer,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                Text('Muestra este código en la tienda', style: textTheme.bodySmall?.copyWith(color: colors.onPrimaryContainer)),
                const SizedBox(height: 4),
                Text(
                  order.code,
                  style: textTheme.headlineMedium?.copyWith(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 3,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          _OrderSummary(store: store, order: order),
          const SizedBox(height: 12),
          _PickupCard(store: store),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Listo')),
        ],
      ),
    );
  }
}

class _OrderSummary extends StatelessWidget {
  const _OrderSummary({required this.store, required this.order});

  final StoreModel store;
  final StoreOrderModel order;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(store.name, style: textTheme.titleMedium),
            if (store.venue != null) Text(store.venue!.name, style: textTheme.bodySmall),
            const SizedBox(height: 8),
            for (final item in order.items) _Row(label: '${item.quantity}× ${item.name}', value: formatBs(item.amount)),
            const Divider(),
            if (order.discountAmount > 0)
              _Row(label: 'Descuentos', value: '− ${formatBs(order.discountAmount)}', color: Colors.red.shade700),
            _Row(label: 'Total', value: formatBs(order.total), emphasize: true),
          ],
        ),
      ),
    );
  }
}

/// Where and when the order is picked up.
class _PickupCard extends StatelessWidget {
  const _PickupCard({required this.store});

  final StoreModel store;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final venue = store.venue;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: colors.outlineVariant),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.storefront_outlined, color: colors.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Retiro en tienda', style: textTheme.titleSmall),
                Text(
                  [
                    if (venue != null) venue.name,
                    if (venue != null && venue.address.isNotEmpty) venue.address,
                    if (venue?.schedule != null) 'Horario ${venue!.schedule}',
                  ].join(' · '),
                  style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                ),
                if (store.phone != null) Text('Contacto: ${store.phone}', style: textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CartLineTile extends StatelessWidget {
  const _CartLineTile({required this.line, required this.onMinus, this.onPlus});

  final CartLine line;
  final VoidCallback onMinus;
  final VoidCallback? onPlus;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final product = line.product;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: StoreImage(
              url: product.photos.firstOrNull,
              icon: productCategoryIcon(product.category?.key ?? ''),
              width: 56,
              height: 56,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: textTheme.bodyMedium),
                const SizedBox(height: 2),
                Text(formatBs(line.amount), style: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          QuantityStepper(value: line.quantity, onMinus: onMinus, onPlus: onPlus),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.emphasize = false, this.color});

  final String label;
  final String value;
  final bool emphasize;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: TextStyle(color: color))),
          const SizedBox(width: 12),
          Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(fontWeight: FontWeight.w600, fontSize: emphasize ? 18 : null, color: color),
          ),
        ],
      ),
    );
  }
}

class _ButtonSpinner extends StatelessWidget {
  const _ButtonSpinner();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2));
  }
}
