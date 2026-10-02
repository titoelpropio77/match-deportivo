import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../../models/team_model.dart';

/// Colours offered for a team; the first is the default badge colour.
const teamColors = <String>[
  '#3949AB',
  '#1E88E5',
  '#00897B',
  '#43A047',
  '#F9A825',
  '#FB8C00',
  '#E53935',
  '#D81B60',
  '#8E24AA',
  '#546E7A',
  '#212121',
];

Color parseTeamColor(String? hex, {Color fallback = const Color(0xFF3949AB)}) {
  final value = hex?.replaceFirst('#', '');
  if (value == null || value.length != 6) return fallback;
  final parsed = int.tryParse(value, radix: 16);
  return parsed == null ? fallback : Color(0xFF000000 | parsed);
}

/// Round team badge: the logo, or the initials on the team colour.
class TeamBadge extends StatelessWidget {
  const TeamBadge({required this.team, this.size = 48, super.key});

  final TeamModel team;
  final double size;

  @override
  Widget build(BuildContext context) {
    return TeamBadgeView(
      initials: team.initials,
      color: team.primaryColor,
      logoUrl: team.logoUrl,
      size: size,
    );
  }
}

/// Same badge from loose values (used by the form preview before the team exists).
class TeamBadgeView extends StatelessWidget {
  const TeamBadgeView({
    required this.initials,
    this.color,
    this.logoUrl,
    this.logoBytes,
    this.size = 48,
    super.key,
  });

  final String initials;
  final String? color;
  final String? logoUrl;
  final List<int>? logoBytes;
  final double size;

  @override
  Widget build(BuildContext context) {
    final background = parseTeamColor(color);
    final foreground = background.computeLuminance() > 0.5 ? Colors.black87 : Colors.white;
    final fallback = Center(
      child: Text(
        initials,
        style: TextStyle(
          color: foreground,
          fontWeight: FontWeight.w800,
          fontSize: size * (initials.length > 2 ? 0.28 : 0.36),
          letterSpacing: 0.5,
        ),
      ),
    );

    Widget child = fallback;
    if (logoBytes != null && logoBytes!.isNotEmpty) {
      child = Image.memory(Uint8List.fromList(logoBytes!), fit: BoxFit.cover);
    } else if (logoUrl != null && logoUrl!.isNotEmpty) {
      child = Image.network(logoUrl!, fit: BoxFit.cover, errorBuilder: (_, _, _) => fallback);
    }

    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: background,
        shape: BoxShape.circle,
        border: Border.all(color: Theme.of(context).colorScheme.surface, width: 2),
        boxShadow: const [BoxShadow(color: Color(0x22000000), blurRadius: 4, offset: Offset(0, 1))],
      ),
      child: child,
    );
  }
}
