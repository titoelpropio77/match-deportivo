import 'user_model.dart';

/// A single player's registration within a match, including their profile.
class MatchPlayerModel {
  const MatchPlayerModel({
    required this.id,
    required this.userId,
    required this.quantitySlots,
    this.user,
  });

  final int id;
  final int userId;
  final int quantitySlots;
  final UserModel? user;

  factory MatchPlayerModel.fromJson(Map<String, dynamic> json) {
    return MatchPlayerModel(
      id: (json['id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      quantitySlots: (json['quantity_slots'] as num).toInt(),
      user: json['user'] != null
          ? UserModel.fromJson(json['user'] as Map<String, dynamic>)
          : null,
    );
  }
}
