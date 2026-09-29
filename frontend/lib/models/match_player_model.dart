import 'user_model.dart';

enum MatchPlayerStatus {
  pending('pending'),
  confirmed('confirmed'),
  reserved('reserved');

  const MatchPlayerStatus(this.value);

  final String value;

  static MatchPlayerStatus fromValue(String? value) {
    if (value == null) return MatchPlayerStatus.confirmed;
    return MatchPlayerStatus.values.firstWhere(
      (status) => status.value == value,
      orElse: () => MatchPlayerStatus.confirmed,
    );
  }
}

/// A single player's registration within a match, including their profile.
class MatchPlayerModel {
  const MatchPlayerModel({
    required this.id,
    required this.userId,
    required this.quantitySlots,
    this.status = MatchPlayerStatus.confirmed,
    this.user,
  });

  final int id;
  final int userId;
  final int quantitySlots;
  final MatchPlayerStatus status;
  final UserModel? user;

  bool get isPending => status == MatchPlayerStatus.pending;

  bool get isReserved => status == MatchPlayerStatus.reserved;

  factory MatchPlayerModel.fromJson(Map<String, dynamic> json) {
    return MatchPlayerModel(
      id: (json['id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      quantitySlots: (json['quantity_slots'] as num).toInt(),
      status: MatchPlayerStatus.fromValue(json['status'] as String?),
      user: json['user'] != null
          ? UserModel.fromJson(json['user'] as Map<String, dynamic>)
          : null,
    );
  }
}
