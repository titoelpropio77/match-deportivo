class SportModel {
  const SportModel({required this.id, required this.key, required this.name});

  final int id;
  final String key;
  final String name;

  factory SportModel.fromJson(Map<String, dynamic> json) {
    return SportModel(
      id: (json['id'] as num).toInt(),
      key: json['key'] as String,
      name: json['name'] as String,
    );
  }
}
