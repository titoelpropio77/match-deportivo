class MatchLevelModel {
  const MatchLevelModel({
    required this.id,
    required this.key,
    required this.name,
    required this.order,
  });

  final int id;
  final String key;
  final String name;
  final int order;

  factory MatchLevelModel.fromJson(Map<String, dynamic> json) {
    return MatchLevelModel(
      id: (json['id'] as num).toInt(),
      key: json['key'] as String,
      name: json['name'] as String,
      order: (json['order'] as num).toInt(),
    );
  }
}
