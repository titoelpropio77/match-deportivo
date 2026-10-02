import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../reserve_court/reserve_courts_screen.dart';

/// Network image with a soft icon placeholder when there is no URL or it fails to load.
class StoreImage extends StatelessWidget {
  const StoreImage({required this.url, required this.icon, this.height, this.width, super.key});

  final String? url;
  final IconData icon;
  final double? height;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final placeholder = ColoredBox(
      color: colors.secondaryContainer,
      child: Center(child: Icon(icon, size: 32, color: colors.onSecondaryContainer)),
    );
    final url = this.url;
    return SizedBox(
      height: height,
      width: width ?? double.infinity,
      child: url == null
          ? placeholder
          : Image.network(url, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
    );
  }
}

/// Final price, with the list price struck through when discounted.
class ProductPrice extends StatelessWidget {
  const ProductPrice({required this.product, this.large = false, super.key});

  final ProductModel product;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final priceStyle = (large ? textTheme.headlineSmall : textTheme.titleSmall)?.copyWith(fontWeight: FontWeight.w700);
    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.end,
      spacing: 6,
      children: [
        Text(formatBs(product.finalPrice), style: priceStyle),
        if (product.hasDiscount)
          Text(
            formatBs(product.price),
            style: (large ? textTheme.bodyMedium : textTheme.bodySmall)?.copyWith(
              color: colors.onSurfaceVariant,
              decoration: TextDecoration.lineThrough,
            ),
          ),
      ],
    );
  }
}

/// Red "-15%" pill on discounted products.
class DiscountBadge extends StatelessWidget {
  const DiscountBadge({required this.percent, super.key});

  final int percent;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(color: Colors.red.shade600, borderRadius: BorderRadius.circular(999)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Text(
          '-$percent%',
          style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}

/// Full-width card of the stores list.
class StoreCard extends StatelessWidget {
  const StoreCard({required this.store, required this.onTap, super.key});

  final StoreModel store;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final venue = store.venue;
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                StoreImage(url: store.imageUrl, icon: Icons.storefront_outlined, height: 140),
                if (store.offersCount > 0)
                  Positioned(
                    right: 12,
                    top: 12,
                    child: DecoratedBox(
                      decoration: BoxDecoration(color: Colors.red.shade600, borderRadius: BorderRadius.circular(999)),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        child: Text(
                          store.offersCount == 1 ? '1 oferta' : '${store.offersCount} ofertas',
                          style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          store.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        store.productsCount == 1 ? '1 producto' : '${store.productsCount} productos',
                        style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                      ),
                    ],
                  ),
                  if (venue != null) ...[
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Icon(Icons.place_outlined, size: 14, color: colors.onSurfaceVariant),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            venue.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (store.categories.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        for (final category in store.categories.take(4)) _CategoryPill(category: category),
                        if (store.categories.length > 4) _MorePill(count: store.categories.length - 4),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryPill extends StatelessWidget {
  const _CategoryPill({required this.category});

  final ProductCategoryModel category;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(color: colors.surfaceContainerHighest, borderRadius: BorderRadius.circular(999)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(productCategoryIcon(category.key), size: 13, color: colors.onSurfaceVariant),
            const SizedBox(width: 4),
            Text(category.name, style: Theme.of(context).textTheme.labelSmall),
          ],
        ),
      ),
    );
  }
}

class _MorePill extends StatelessWidget {
  const _MorePill({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(color: colors.surfaceContainerHighest, borderRadius: BorderRadius.circular(999)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Text('+$count', style: Theme.of(context).textTheme.labelSmall),
      ),
    );
  }
}

/// Grid tile of a product with its photo, price and how many the user has in the cart.
class ProductCard extends StatelessWidget {
  const ProductCard({required this.product, required this.inCart, required this.onTap, this.onAdd, super.key});

  final ProductModel product;
  final int inCart;
  final VoidCallback onTap;

  /// Adds one unit; null when no more can be added.
  final VoidCallback? onAdd;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: inCart > 0 ? colors.primary : colors.outlineVariant, width: inCart > 0 ? 1.5 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Opacity(
                    opacity: product.soldOut ? 0.4 : 1,
                    child: StoreImage(
                      url: product.photos.firstOrNull,
                      icon: productCategoryIcon(product.category?.key ?? ''),
                    ),
                  ),
                  if (product.hasDiscount && !product.soldOut)
                    Positioned(left: 8, top: 8, child: DiscountBadge(percent: product.discountPercent)),
                  if (product.soldOut)
                    Center(
                      child: DecoratedBox(
                        decoration: BoxDecoration(color: Colors.black87, borderRadius: BorderRadius.circular(6)),
                        child: const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          child: Text('AGOTADO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                        ),
                      ),
                    ),
                  if (inCart > 0)
                    Positioned(
                      right: 8,
                      top: 8,
                      child: CircleAvatar(
                        radius: 13,
                        backgroundColor: colors.primary,
                        child: Text('$inCart', style: TextStyle(color: colors.onPrimary, fontSize: 12, fontWeight: FontWeight.w700)),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 4, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodyMedium?.copyWith(height: 1.2),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(child: ProductPrice(product: product)),
                      if (!product.soldOut)
                        IconButton.filledTonal(
                          visualDensity: VisualDensity.compact,
                          iconSize: 20,
                          tooltip: 'Agregar',
                          onPressed: onAdd,
                          icon: const Icon(Icons.add),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// − value + control used for quantities.
class QuantityStepper extends StatelessWidget {
  const QuantityStepper({required this.value, this.onMinus, this.onPlus, super.key});

  final int value;
  final VoidCallback? onMinus;
  final VoidCallback? onPlus;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border.all(color: colors.outlineVariant),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            visualDensity: VisualDensity.compact,
            tooltip: 'Quitar uno',
            onPressed: onMinus,
            icon: const Icon(Icons.remove),
          ),
          SizedBox(
            width: 28,
            child: Text('$value', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            tooltip: 'Agregar uno',
            onPressed: onPlus,
            icon: const Icon(Icons.add),
          ),
        ],
      ),
    );
  }
}
