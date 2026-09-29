class RatingTagModel {
  const RatingTagModel({
    required this.id,
    required this.sportId,
    required this.key,
    required this.label,
    required this.polarity,
    required this.marksAbsence,
  });

  final int id;
  final int sportId;
  final String key;
  final String label;
  final String polarity;
  final bool marksAbsence;

  bool get isNegative => polarity == 'negative';

  bool get isPositive => polarity == 'positive';

  factory RatingTagModel.fromJson(Map<String, dynamic> json) {
    return RatingTagModel(
      id: (json['id'] as num).toInt(),
      sportId: (json['sport_id'] as num).toInt(),
      key: json['key'] as String,
      label: json['label'] as String,
      polarity: json['polarity'] as String,
      marksAbsence: json['marks_absence'] == true || json['marks_absence'] == 1,
    );
  }
}
