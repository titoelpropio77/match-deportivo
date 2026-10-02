import 'sport_model.dart';

/// Sports gear a sports center rents with its courts (ball, racket...), for one sport.
class RentalItemModel {
  const RentalItemModel({
    required this.id,
    required this.sportId,
    required this.name,
    required this.price,
    this.priceType = 'per_hour',
    this.description,
    this.stock,
    this.sport,
  });

  final int id;
  final int sportId;
  final String name;
  final double price;

  /// `per_hour` (price × hours) or `flat` (once per reservation).
  final String priceType;
  final String? description;

  /// Units the center owns; null = unlimited.
  final int? stock;
  final SportModel? sport;

  bool get isFlat => priceType == 'flat';

  double amountFor(int quantity, int hours) => price * quantity * (isFlat ? 1 : hours);

  factory RentalItemModel.fromJson(Map<String, dynamic> json) {
    final sport = json['sport'] as Map<String, dynamic>?;
    return RentalItemModel(
      id: (json['id'] as num).toInt(),
      sportId: (json['sport_id'] as num).toInt(),
      name: json['name'] as String,
      price: (json['price'] as num).toDouble(),
      priceType: json['price_type'] as String? ?? 'per_hour',
      description: json['description'] as String?,
      stock: (json['stock'] as num?)?.toInt(),
      sport: sport == null ? null : SportModel.fromJson(sport),
    );
  }
}

/// Gear picked for one booked range.
class RentalSelection {
  const RentalSelection({required this.item, required this.quantity});

  final RentalItemModel item;
  final int quantity;

  Map<String, dynamic> toJson() => {'rental_item_id': item.id, 'quantity': quantity};
}

/// Gear rented with a reservation, as stored (name and price at booking time).
class ReservedRentalModel {
  const ReservedRentalModel({
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.priceType,
    required this.amount,
  });

  final String name;
  final int quantity;
  final double unitPrice;
  final String priceType;
  final double amount;

  factory ReservedRentalModel.fromJson(Map<String, dynamic> json) {
    return ReservedRentalModel(
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unitPrice: (json['unit_price'] as num).toDouble(),
      priceType: json['price_type'] as String? ?? 'per_hour',
      amount: (json['amount'] as num).toDouble(),
    );
  }
}
