import 'sport_model.dart';

class UserModel {
  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.nickname,
    this.phone,
    this.preferredPosition,
    this.gender,
    this.birthDate,
    this.photoUrl,
    this.createdAt,
    this.favoriteSports = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? nickname;
  final String? phone;
  final String? preferredPosition;
  final String? gender;
  final DateTime? birthDate;
  final String? photoUrl;
  final DateTime? createdAt;

  /// "Mis deportes favoritos" (only sent by auth and profile endpoints).
  final List<SportModel> favoriteSports;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      // Omitted by endpoints that hide contact data (e.g. another player's profile).
      email: json['email'] as String? ?? '',
      nickname: json['nickname'] as String?,
      phone: json['phone'] as String?,
      preferredPosition: json['preferred_position'] as String?,
      gender: json['gender'] as String?,
      birthDate: DateTime.tryParse(json['birth_date'] as String? ?? ''),
      photoUrl: json['photo_url'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      favoriteSports: (json['favorite_sports'] as List<dynamic>? ?? const [])
          .map((item) => SportModel.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'nickname': nickname,
      'phone': phone,
      'preferred_position': preferredPosition,
      'gender': gender,
      if (birthDate != null)
        'birth_date':
            '${birthDate!.year.toString().padLeft(4, '0')}-${birthDate!.month.toString().padLeft(2, '0')}-${birthDate!.day.toString().padLeft(2, '0')}',
      'photo_url': photoUrl,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
      'favorite_sports': [
        for (final sport in favoriteSports) {'id': sport.id, 'key': sport.key, 'name': sport.name},
      ],
    };
  }
}
