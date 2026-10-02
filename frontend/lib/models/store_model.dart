import 'package:flutter/material.dart';

/// Kind of product a store sells (balones, calzado, bebidas...).
class ProductCategoryModel {
  const ProductCategoryModel({required this.id, required this.key, required this.name, this.storesCount});

  final int id;
  final String key;
  final String name;

  /// Active stores that sell it (only in `GET product-categories`).
  final int? storesCount;

  factory ProductCategoryModel.fromJson(Map<String, dynamic> json) {
    return ProductCategoryModel(
      id: (json['id'] as num).toInt(),
      key: json['key'] as String,
      name: json['name'] as String,
      storesCount: (json['stores_count'] as num?)?.toInt(),
    );
  }
}

class StoreVenueModel {
  const StoreVenueModel({
    required this.id,
    required this.name,
    required this.address,
    this.openingTime,
    this.closingTime,
    this.cityName,
    this.photos = const [],
  });

  final int id;
  final String name;
  final String address;
  final String? openingTime;
  final String? closingTime;
  final String? cityName;
  final List<String> photos;

  String? get schedule => openingTime != null && closingTime != null ? '$openingTime–$closingTime' : null;

  factory StoreVenueModel.fromJson(Map<String, dynamic> json) {
    final city = json['city'] as Map<String, dynamic>?;
    return StoreVenueModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      address: json['address'] as String? ?? '',
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      cityName: city?['name'] as String?,
      photos: (json['photos'] as List<dynamic>?)?.map((item) => item as String).toList() ?? const [],
    );
  }
}

/// Shop of a sports center. Purchases are picked up at the store.
class StoreModel {
  const StoreModel({
    required this.id,
    required this.name,
    this.description,
    this.phone,
    this.coverUrl,
    this.categories = const [],
    this.productsCount = 0,
    this.offersCount = 0,
    this.venue,
  });

  final int id;
  final String name;
  final String? description;
  final String? phone;
  final String? coverUrl;
  final List<ProductCategoryModel> categories;
  final int productsCount;

  /// Products currently on sale.
  final int offersCount;
  final StoreVenueModel? venue;

  /// Own cover, or the sports center's first photo.
  String? get imageUrl => coverUrl ?? venue?.photos.firstOrNull;

  factory StoreModel.fromJson(Map<String, dynamic> json) {
    final venue = json['venue'] as Map<String, dynamic>?;
    return StoreModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      description: json['description'] as String?,
      phone: json['phone'] as String?,
      coverUrl: json['cover_url'] as String?,
      categories: (json['categories'] as List<dynamic>? ?? const [])
          .map((item) => ProductCategoryModel.fromJson(item as Map<String, dynamic>))
          .toList(),
      productsCount: (json['products_count'] as num?)?.toInt() ?? 0,
      offersCount: (json['offers_count'] as num?)?.toInt() ?? 0,
      venue: venue == null ? null : StoreVenueModel.fromJson(venue),
    );
  }
}

class ProductModel {
  const ProductModel({
    required this.id,
    required this.storeId,
    required this.name,
    required this.price,
    required this.finalPrice,
    required this.available,
    this.discountPercent = 0,
    this.sku,
    this.description,
    this.category,
    this.photos = const [],
  });

  final int id;
  final int storeId;
  final String name;

  /// List price.
  final double price;

  /// Price after the discount.
  final double finalPrice;

  /// Units the user can buy now.
  final int available;
  final int discountPercent;
  final String? sku;
  final String? description;
  final ProductCategoryModel? category;
  final List<String> photos;

  bool get hasDiscount => discountPercent > 0;
  bool get soldOut => available <= 0;

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    final category = json['category'] as Map<String, dynamic>?;
    return ProductModel(
      id: (json['id'] as num).toInt(),
      storeId: (json['store_id'] as num).toInt(),
      name: json['name'] as String,
      price: (json['price'] as num).toDouble(),
      finalPrice: (json['final_price'] as num).toDouble(),
      available: (json['available'] as num).toInt(),
      discountPercent: (json['discount_percent'] as num?)?.toInt() ?? 0,
      sku: json['sku'] as String?,
      description: json['description'] as String?,
      category: category == null ? null : ProductCategoryModel.fromJson(category),
      photos: (json['photos'] as List<dynamic>?)?.map((item) => item as String).toList() ?? const [],
    );
  }
}

/// Product and quantity in the cart of a store.
class CartLine {
  const CartLine({required this.product, required this.quantity});

  final ProductModel product;
  final int quantity;

  double get amount => product.finalPrice * quantity;
  double get listAmount => product.price * quantity;
}

class StoreOrderItemModel {
  const StoreOrderItemModel({
    required this.name,
    required this.unitPrice,
    required this.quantity,
    required this.amount,
    this.productId,
    this.listPrice,
    this.discountPercent = 0,
    this.photoUrl,
  });

  final int? productId;
  final String name;
  final double? listPrice;
  final int discountPercent;
  final double unitPrice;
  final int quantity;
  final double amount;
  final String? photoUrl;

  factory StoreOrderItemModel.fromJson(Map<String, dynamic> json) {
    return StoreOrderItemModel(
      productId: (json['product_id'] as num?)?.toInt(),
      name: json['name'] as String,
      listPrice: (json['list_price'] as num?)?.toDouble(),
      discountPercent: (json['discount_percent'] as num?)?.toInt() ?? 0,
      unitPrice: (json['unit_price'] as num).toDouble(),
      quantity: (json['quantity'] as num).toInt(),
      amount: (json['amount'] as num).toDouble(),
      photoUrl: json['photo_url'] as String?,
    );
  }
}

/// Purchase at a store: pending payment (holds the units 15 min), paid (ready for pickup until
/// delivered) or cancelled.
class StoreOrderModel {
  const StoreOrderModel({
    required this.id,
    required this.code,
    required this.status,
    required this.subtotal,
    required this.discountAmount,
    required this.total,
    this.items = const [],
    this.createdAt,
    this.paymentExpiresAt,
    this.paidAt,
    this.deliveredAt,
    this.cancelledByVenue = false,
    this.cancellationReason,
    this.refundedAt,
    this.notes,
    this.store,
  });

  final int id;
  final String code;

  /// `pending_payment`, `paid` or `cancelled`.
  final String status;
  final double subtotal;
  final double discountAmount;
  final double total;
  final List<StoreOrderItemModel> items;
  final DateTime? createdAt;
  final DateTime? paymentExpiresAt;
  final DateTime? paidAt;
  final DateTime? deliveredAt;
  final bool cancelledByVenue;
  final String? cancellationReason;
  final DateTime? refundedAt;
  final String? notes;
  final StoreModel? store;

  bool get isPending => status == 'pending_payment';
  bool get isPaid => status == 'paid';
  bool get isCancelled => status == 'cancelled';
  bool get isDelivered => isPaid && deliveredAt != null;
  bool get canPay => isPending && (paymentExpiresAt?.isAfter(DateTime.now()) ?? false);
  int get unitsCount => items.fold(0, (sum, item) => sum + item.quantity);

  factory StoreOrderModel.fromJson(Map<String, dynamic> json) {
    final store = json['store'] as Map<String, dynamic>?;
    DateTime? parse(String key) {
      final value = json[key] as String?;
      return value == null ? null : DateTime.parse(value).toLocal();
    }

    return StoreOrderModel(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String,
      status: json['status'] as String,
      subtotal: (json['subtotal'] as num).toDouble(),
      discountAmount: (json['discount_amount'] as num?)?.toDouble() ?? 0,
      total: (json['total'] as num).toDouble(),
      items: (json['items'] as List<dynamic>? ?? const [])
          .map((item) => StoreOrderItemModel.fromJson(item as Map<String, dynamic>))
          .toList(),
      createdAt: parse('created_at'),
      paymentExpiresAt: parse('payment_expires_at'),
      paidAt: parse('paid_at'),
      deliveredAt: parse('delivered_at'),
      cancelledByVenue: json['cancelled_by_venue'] as bool? ?? false,
      cancellationReason: json['cancellation_reason'] as String?,
      refundedAt: parse('refunded_at'),
      notes: json['notes'] as String?,
      store: store == null ? null : StoreModel.fromJson(store),
    );
  }
}

/// Icon of a product category (keys of the default catalog; others get a generic bag).
IconData productCategoryIcon(String key) => switch (key) {
      'balls' => Icons.sports_soccer_outlined,
      'rackets' => Icons.sports_tennis_outlined,
      'footwear' => Icons.directions_run_outlined,
      'apparel' => Icons.checkroom_outlined,
      'protection' => Icons.shield_outlined,
      'accessories' => Icons.watch_outlined,
      'training' => Icons.fitness_center_outlined,
      'drinks' => Icons.local_drink_outlined,
      'snacks' => Icons.cookie_outlined,
      'supplements' => Icons.medication_outlined,
      _ => Icons.shopping_bag_outlined,
    };
