import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../../services/store_api_service.dart';
import '../reserve_court/court_payment_screen.dart' show formatReservationDate;
import '../reserve_court/reserve_courts_screen.dart';
import 'store_checkout_screen.dart';
import 'store_widgets.dart';

/// Store purchases of the user, newest first, with the pickup code and status.
class MyOrdersScreen extends StatefulWidget {
  const MyOrdersScreen({required this.storeApiService, super.key});

  final StoreApiService storeApiService;

  @override
  State<MyOrdersScreen> createState() => _MyOrdersScreenState();
}

class _MyOrdersScreenState extends State<MyOrdersScreen> {
  List<StoreOrderModel> _orders = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final orders = await widget.storeApiService.myOrders();
      if (!mounted) return;
      setState(() {
        _orders = orders;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is StoreApiException ? error.message : 'No pudimos cargar tus compras.';
        _loading = false;
      });
    }
  }

  Future<void> _pay(StoreOrderModel order) async {
    final store = order.store;
    if (store == null) return;
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => StoreCheckoutScreen(store: store, order: order, storeApiService: widget.storeApiService),
      ),
    );
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis compras')),
      body: RefreshIndicator(onRefresh: _load, child: _body()),
    );
  }

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 64),
          Text(_error!, textAlign: TextAlign.center),
          Center(child: TextButton(onPressed: _load, child: const Text('Reintentar'))),
        ],
      );
    }
    if (_orders.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 80),
          Icon(Icons.shopping_bag_outlined, size: 48),
          SizedBox(height: 12),
          Text('Aún no compraste en las tiendas.', textAlign: TextAlign.center),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _orders.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) => StoreOrderCard(order: _orders[index], onPay: () => _pay(_orders[index])),
    );
  }
}

class StoreOrderCard extends StatelessWidget {
  const StoreOrderCard({required this.order, required this.onPay, super.key});

  final StoreOrderModel order;
  final VoidCallback onPay;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final store = order.store;
    final (statusLabel, statusColor) = switch (order.status) {
      'paid' when order.isDelivered => ('Entregado', colors.onSurfaceVariant),
      'paid' => ('Pagado · listo para retirar', Colors.green.shade700),
      'pending_payment' => ('Pago pendiente', Colors.orange.shade800),
      'cancelled' => (
          order.refundedAt != null ? 'Anulado por la tienda · reembolsado' : 'Anulado por la tienda',
          colors.error,
        ),
      _ => (order.status, colors.onSurfaceVariant),
    };
    final photo = order.items.map((item) => item.photoUrl).nonNulls.firstOrNull;
    final summary = order.items.map((item) => '${item.quantity}× ${item.name}').join(', ');

    return Opacity(
      opacity: order.isDelivered ? 0.7 : 1,
      child: Card(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: colors.outlineVariant),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 88,
              height: 128,
              child: StoreImage(url: photo ?? store?.imageUrl, icon: Icons.shopping_bag_outlined),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            store?.name ?? 'Tienda',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: textTheme.titleSmall,
                          ),
                        ),
                        Text(order.code, style: textTheme.labelMedium?.copyWith(letterSpacing: 1)),
                      ],
                    ),
                    if (store?.venue != null)
                      Text(
                        store!.venue!.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                      ),
                    const SizedBox(height: 6),
                    Text(summary, maxLines: 2, overflow: TextOverflow.ellipsis, style: textTheme.bodySmall),
                    Text(
                      [
                        if (order.createdAt != null) formatReservationDate(order.createdAt!),
                        formatBs(order.total),
                      ].join(' · '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: textTheme.bodySmall,
                    ),
                    const SizedBox(height: 6),
                    Text(statusLabel, style: textTheme.labelMedium?.copyWith(color: statusColor)),
                    if (order.isCancelled && order.cancellationReason != null)
                      Text(
                        'Motivo: ${order.cancellationReason}',
                        style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                      ),
                    if (order.canPay)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(onPressed: onPay, child: const Text('Pagar')),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
