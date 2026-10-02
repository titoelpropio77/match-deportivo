import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../../services/store_api_service.dart';
import '../reserve_court/reserve_courts_screen.dart';
import 'product_detail_screen.dart';
import 'store_cart.dart';
import 'store_checkout_screen.dart';
import 'store_widgets.dart';

/// Store presentation, its products by category and the cart bar to check out.
class StoreDetailScreen extends StatefulWidget {
  const StoreDetailScreen({required this.store, required this.storeApiService, super.key});

  final StoreModel store;
  final StoreApiService storeApiService;

  @override
  State<StoreDetailScreen> createState() => _StoreDetailScreenState();
}

class _StoreDetailScreenState extends State<StoreDetailScreen> {
  /// Filter value for discounted products.
  static const _offers = -1;

  final _cart = StoreCart();
  List<ProductModel> _products = const [];
  int? _filter;
  bool _loading = true;
  String? _error;

  StoreModel get _store => widget.store;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _cart.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final products = await widget.storeApiService.products(_store.id);
      if (!mounted) return;
      _cart.refresh(products);
      setState(() {
        _products = products;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is StoreApiException ? error.message : 'No pudimos cargar los productos.';
        _loading = false;
      });
    }
  }

  /// Categories with products, in the store's order.
  List<ProductCategoryModel> get _categories {
    final used = _products.map((product) => product.category?.id).toSet();
    return _store.categories.where((category) => used.contains(category.id)).toList();
  }

  List<ProductModel> get _visible {
    final filtered = switch (_filter) {
      null => _products,
      _offers => _products.where((product) => product.hasDiscount),
      final id => _products.where((product) => product.category?.id == id),
    };
    // Sold out products go last.
    return [...filtered.where((product) => !product.soldOut), ...filtered.where((product) => product.soldOut)];
  }

  Future<void> _openProduct(ProductModel product) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ProductDetailScreen(product: product, cart: _cart)),
    );
  }

  Future<void> _checkout() async {
    final paid = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => StoreCheckoutScreen(store: _store, cart: _cart, storeApiService: widget.storeApiService),
      ),
    );
    if (!mounted) return;
    if (paid == true) _cart.clear();
    _load();
  }

  Future<bool> _confirmLeave() async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Salir de la tienda?'),
        content: const Text('Se vaciará tu carrito.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Seguir comprando')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Salir')),
        ],
      ),
    );
    return leave ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final venue = _store.venue;
    final categories = _categories;
    final hasOffers = _products.any((product) => product.hasDiscount);

    return ListenableBuilder(
      listenable: _cart,
      builder: (context, _) => PopScope(
        canPop: _cart.isEmpty,
        onPopInvokedWithResult: (didPop, _) async {
          if (didPop) return;
          if (await _confirmLeave() && context.mounted) {
            _cart.clear();
            Navigator.of(context).pop();
          }
        },
        child: Scaffold(
          bottomNavigationBar: _cart.isEmpty ? null : _CartBar(cart: _cart, onCheckout: _checkout),
          body: RefreshIndicator(
            onRefresh: _load,
            child: CustomScrollView(
              slivers: [
                SliverAppBar(
                  pinned: true,
                  expandedHeight: 200,
                  foregroundColor: Colors.white,
                  backgroundColor: colors.inverseSurface,
                  title: Text(_store.name),
                  flexibleSpace: FlexibleSpaceBar(
                    background: Stack(
                      fit: StackFit.expand,
                      children: [
                        StoreImage(url: _store.imageUrl, icon: Icons.storefront_outlined),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Colors.black.withValues(alpha: 0.5), Colors.transparent, Colors.black.withValues(alpha: 0.3)],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  sliver: SliverList.list(
                    children: [
                      Text(_store.name, style: textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
                      if (venue != null) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(Icons.place_outlined, size: 16, color: colors.onSurfaceVariant),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                [venue.name, if (venue.schedule != null) venue.schedule!].join(' · '),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                              ),
                            ),
                          ],
                        ),
                      ],
                      if (_store.description != null && _store.description!.trim().isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Text(_store.description!, style: textTheme.bodyMedium?.copyWith(height: 1.4)),
                      ],
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: colors.secondaryContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.storefront_outlined, size: 18, color: colors.onSecondaryContainer),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Pagas con QR y retiras tu pedido en la tienda.',
                                style: textTheme.bodySmall?.copyWith(color: colors.onSecondaryContainer),
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (!_loading && _error == null && (categories.length > 1 || hasOffers)) ...[
                        const SizedBox(height: 12),
                        SizedBox(
                          height: 40,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            children: [
                              _FilterChip(label: 'Todo', selected: _filter == null, onSelected: () => setState(() => _filter = null)),
                              if (hasOffers)
                                _FilterChip(
                                  label: 'Ofertas',
                                  icon: Icons.local_offer_outlined,
                                  selected: _filter == _offers,
                                  onSelected: () => setState(() => _filter = _offers),
                                ),
                              for (final category in categories)
                                _FilterChip(
                                  label: category.name,
                                  icon: productCategoryIcon(category.key),
                                  selected: _filter == category.id,
                                  onSelected: () => setState(() => _filter = category.id),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (_loading)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_error != null)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        TextButton(onPressed: _load, child: const Text('Reintentar')),
                      ],
                    ),
                  )
                else if (_products.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: Center(child: Text('Esta tienda todavía no tiene productos.')),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    sliver: SliverGrid.builder(
                      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                        maxCrossAxisExtent: 220,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.68,
                      ),
                      itemCount: _visible.length,
                      itemBuilder: (context, index) {
                        final product = _visible[index];
                        return ProductCard(
                          product: product,
                          inCart: _cart.quantityOf(product.id),
                          onTap: () => _openProduct(product),
                          onAdd: _cart.canAdd(product) ? () => _cart.add(product) : null,
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onSelected, this.icon});

  final String label;
  final IconData? icon;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        avatar: icon == null ? null : Icon(icon, size: 18),
        label: Text(label),
        selected: selected,
        onSelected: (_) => onSelected(),
      ),
    );
  }
}

/// Bottom bar with the cart summary and the checkout button.
class _CartBar extends StatelessWidget {
  const _CartBar({required this.cart, required this.onCheckout});

  final StoreCart cart;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 12, 16, 12),
        decoration: BoxDecoration(
          color: colors.surface,
          border: Border(top: BorderSide(color: colors.outlineVariant)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(formatBs(cart.total), style: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                  Text(
                    cart.units == 1 ? '1 producto en tu carrito' : '${cart.units} productos en tu carrito',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            FilledButton.icon(
              onPressed: onCheckout,
              icon: const Icon(Icons.shopping_cart_checkout),
              label: const Text('Ver carrito'),
            ),
          ],
        ),
      ),
    );
  }
}
