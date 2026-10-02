import 'package:flutter/material.dart';

/// Where tapping a home banner leads. Keep in sync with the backend `Banner::LINK_TYPES`.
enum BannerLinkType {
  none('none'),
  reserveCourts('reserve_courts'),
  court('court'),
  tournaments('tournaments'),
  tournament('tournament'),
  teams('teams'),
  stores('stores'),
  eventSpaces('event_spaces'),
  url('url');

  const BannerLinkType(this.value);

  final String value;

  /// Unknown types (added later on the server) behave as "no link" instead of crashing.
  static BannerLinkType fromValue(Object? value) {
    for (final type in values) {
      if (type.value == value) return type;
    }
    return BannerLinkType.none;
  }
}

/// Destination of a banner: an app section, a record (tournament/center) or an external URL.
class BannerLink {
  const BannerLink({required this.type, this.id, this.url});

  final BannerLinkType type;
  final int? id;
  final String? url;

  bool get isActionable => type != BannerLinkType.none;

  factory BannerLink.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const BannerLink(type: BannerLinkType.none);
    return BannerLink(
      type: BannerLinkType.fromValue(json['type']),
      id: (json['id'] as num?)?.toInt(),
      url: json['url'] as String?,
    );
  }
}

/// Slide of the home carousel, from `GET /api/banners`.
class BannerModel {
  const BannerModel({
    required this.id,
    required this.title,
    required this.link,
    this.subtitle,
    this.buttonLabel,
    this.imageUrl,
    this.backgroundColor = const Color(0xFF4F46E5),
  });

  final int id;
  final String title;
  final String? subtitle;
  final String? buttonLabel;
  final String? imageUrl;
  final Color backgroundColor;
  final BannerLink link;

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      subtitle: json['subtitle'] as String?,
      buttonLabel: json['button_label'] as String?,
      imageUrl: json['image_url'] as String?,
      backgroundColor:
          parseHexColor(json['background_color'] as String?) ??
          const Color(0xFF4F46E5),
      link: BannerLink.fromJson(json['link'] as Map<String, dynamic>?),
    );
  }

  /// "#RRGGBB" → Color; null when missing or malformed.
  static Color? parseHexColor(String? hex) {
    if (hex == null) return null;
    final match = RegExp(r'^#?([0-9a-fA-F]{6})$').firstMatch(hex.trim());
    if (match == null) return null;
    return Color(int.parse('FF${match.group(1)}', radix: 16));
  }
}
