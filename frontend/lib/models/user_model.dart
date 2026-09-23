class UserModel {
  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.nickname,
    this.phone,
    this.preferredPosition,
    this.createdAt,
  });

  final int id;
  final String name;
  final String email;
  final String? nickname;
  final String? phone;
  final String? preferredPosition;
  final DateTime? createdAt;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      email: json['email'] as String,
      nickname: json['nickname'] as String?,
      phone: json['phone'] as String?,
      preferredPosition: json['preferred_position'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
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
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
    };
  }
}
