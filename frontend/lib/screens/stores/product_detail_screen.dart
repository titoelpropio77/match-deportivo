import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../reserve_court/reserve_courts_screen.dart';
import 'store_cart.dart';
import 'store_widgets.dart';

/// Photo gallery, price, availability and description of a product, with the quantity to add.
class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({required this.product, required this.cart, super.key});

  final ProductModel product;
  final StoreCart cart;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late int _quantity = widget.cart.quantityOf(widget.product.id).clamp(widget.product.soldOut ? 0 : 1, widget.product.available);
  int _page = 0;

  ProductModel get _product => widget.product;
  bool get _inCart => widget.cart.quantityOf(_product.id) > 0;

  void _save() {
    widget.cart.set(_product, _quantity);
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final photos = _product.photos;
    final icon = productCategoryIcon(_product.category?.key ?? '');
    final available = _product.available;
    final (stockLabel, stockColor) = switch (available) {
      0 => ('Agotado', colors.error),
      <= 5 => (available == 1 ? '¡Queda 1 unidad!' : '¡Quedan $available unidades!', Colors.orange.shade800),
      _ => ('Disponible', Colors.green.shade700),
    };

    return Scaffold(
      appBar: AppBar(title: Text(_product.category?.name ?? 'Producto')),
      bottomNavigationBar: _product.soldOut
          ? null
          : SafeArea(
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                decoration: BoxDecoration(
                  color: colors.surface,
                  border: Border(top: BorderSide(color: colors.outlineVariant)),
                ),
                child: Row(
                  children: [
                    QuantityStepper(
                      value: _quantity,
                      onMinus: _quantity > (_inCart ? 0 : 1) ? () => setState(() => _quantity--) : null,
                      onPlus: _quantity < available ? () => setState(() => _quantity++) : null,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: _save,
                        child: Text(
                          _quantity == 0
                              ? 'Quitar del carrito'
                              : '${_inCart ? 'Actualizar' : 'Agregar'} · ${formatBs(_product.finalPrice * _quantity)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
      body: ListView(
        children: [
          AspectRatio(
            aspectRatio: 1,
            child: Stack(
              children: [
                if (photos.isEmpty)
                  StoreImage(url: null, icon: icon)
                else
                  PageView.builder(
                    itemCount: photos.length,
                    onPageChanged: (page) => setState(() => _page = page),
                    itemBuilder: (context, index) => StoreImage(url: photos[index], icon: icon),
                  ),
                if (_product.hasDiscount)
                  Positioned(left: 16, top: 16, child: DiscountBadge(percent: _product.discountPercent)),
                if (photos.length > 1)
                  Positioned(
                    bottom: 12,
                    left: 0,
                    right: 0,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (var index = 0; index < photos.length; index++)
                          AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            width: index == _page ? 18 : 7,
                            height: 7,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: index == _page ? 1 : 0.6),
                              borderRadius: BorderRadius.circular(999),
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_product.name, style: textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w600)),
                if (_product.sku != null) ...[
                  const SizedBox(height: 2),
                  Text('Código ${_product.sku}', style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant)),
                ],
                const SizedBox(height: 12),
                ProductPrice(product: _product, large: true),
                if (_product.hasDiscount)
                  Text(
                    'Ahorras ${formatBs(_product.price - _product.finalPrice)} por unidad',
                    style: textTheme.bodySmall?.copyWith(color: Colors.red.shade700),
                  ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Icon(available == 0 ? Icons.block : Icons.inventory_2_outlined, size: 18, color: stockColor),
                    const SizedBox(width: 6),
                    Text(stockLabel, style: textTheme.labelLarge?.copyWith(color: stockColor)),
                  ],
                ),
                if (_product.description != null && _product.description!.trim().isNotEmpty) ...[
                  const SizedBox(height: 20),
                  Text('Descripción', style: textTheme.titleSmall),
                  const SizedBox(height: 6),
                  Text(_product.description!, style: textTheme.bodyMedium?.copyWith(height: 1.45)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
