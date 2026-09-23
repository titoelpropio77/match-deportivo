import 'package:flutter/material.dart';

import '../../data/dummy_featured_courts.dart';
import '../../models/featured_court_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/user_api_service.dart';
import '../create_match_screen.dart';
import '../placeholder/coming_soon_screen.dart';
import '../search_teams/search_teams_screen.dart';
import 'widgets/dashboard_header.dart';
import 'widgets/featured_courts_section.dart';
import 'widgets/promo_banner.dart';
import 'widgets/quick_action_item.dart';
import 'widgets/quick_actions_grid.dart';

/// Main "Inicio" tab: greeting, promo banner, quick actions and featured courts.
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    required this.userName,
    required this.currentUserId,
    required this.matchApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.courtApiService,
    required this.onOpenProfile,
    super.key,
  });

  final String userName;
  final int currentUserId;
  final MatchApiService matchApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final CourtApiService courtApiService;
  final VoidCallback onOpenProfile;

  void _openComingSoon(BuildContext context, String title) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)),
    );
  }

  void _openReservarCancha(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CreateMatchScreen(
          matchApiService: matchApiService,
          userApiService: userApiService,
          sportApiService: sportApiService,
          matchLevelApiService: matchLevelApiService,
          courtApiService: courtApiService,
        ),
      ),
    );
  }

  void _openBuscarEquipos(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SearchTeamsScreen(
          matchApiService: matchApiService,
          currentUserId: currentUserId,
        ),
      ),
    );
  }

  void _showNoNotifications(BuildContext context) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(const SnackBar(content: Text('No tienes notificaciones nuevas.')));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          DashboardHeader(
            userName: userName,
            location: 'Santa Cruz, Bolivia',
            onNotificationsTap: () => _showNoNotifications(context),
            onAvatarTap: onOpenProfile,
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                PromoBanner(onReservePressed: () => _openReservarCancha(context)),
                const SizedBox(height: 24),
                QuickActionsGrid(
                  actions: [
                    QuickActionData(
                      icon: Icons.event_available_rounded,
                      label: 'Reservar\nCancha',
                      onTap: () => _openReservarCancha(context),
                    ),
                    QuickActionData(
                      icon: Icons.emoji_events_outlined,
                      label: 'Torneos',
                      onTap: () => _openComingSoon(context, 'Torneos'),
                    ),
                    QuickActionData(
                      icon: Icons.groups_outlined,
                      label: 'Equipos',
                      onTap: () => _openComingSoon(context, 'Equipos'),
                    ),
                    QuickActionData(
                      icon: Icons.leaderboard_outlined,
                      label: 'Ranking',
                      onTap: () => _openComingSoon(context, 'Ranking'),
                    ),
                    QuickActionData(
                      icon: Icons.scoreboard_outlined,
                      label: 'Partidos /\nResultados',
                      onTap: () => _openComingSoon(context, 'Partidos / Resultados'),
                    ),
                    QuickActionData(
                      icon: Icons.person_search_outlined,
                      label: 'Buscar\nEquipos',
                      onTap: () => _openBuscarEquipos(context),
                    ),
                  ],
                ),
                const SizedBox(height: 28),
                FeaturedCourtsSection(
                  courts: dummyFeaturedCourts,
                  onCourtPressed: (FeaturedCourtModel court) =>
                      _openComingSoon(context, court.name),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
