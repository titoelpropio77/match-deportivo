import 'package:flutter/material.dart';

import '../../data/dummy_featured_courts.dart';
import '../../models/court_field_model.dart';
import '../../models/featured_court_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/sport_api_service.dart';
import '../my_courts/my_courts_screen.dart';
import '../my_reservations/my_reservations_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import '../placeholder/coming_soon_screen.dart';
import '../search_teams/search_teams_screen.dart';
import 'widgets/dashboard_header.dart';
import 'widgets/featured_courts_section.dart';
import 'widgets/my_courts_section.dart';
import 'widgets/my_reservations_section.dart';
import 'widgets/promo_banner.dart';
import 'widgets/quick_action_item.dart';
import 'widgets/quick_actions_grid.dart';

/// Main "Inicio" tab: greeting, promo banner, quick actions and featured courts.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    required this.userName,
    this.photoUrl,
    required this.currentUserId,
    required this.matchApiService,
    required this.sportApiService,
    required this.courtApiService,
    required this.onOpenProfile,
    super.key,
  });

  final String userName;
  final String? photoUrl;
  final int currentUserId;
  final MatchApiService matchApiService;
  final SportApiService sportApiService;
  final CourtApiService courtApiService;
  final VoidCallback onOpenProfile;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  List<CourtReservationModel> _reservations = const [];

  @override
  void initState() {
    super.initState();
    _loadReservations();
  }

  /// The "Mis reservas" entry only appears when the user has reservations; failures just hide it.
  Future<void> _loadReservations() async {
    try {
      final reservations = await widget.courtApiService.myReservations();
      if (mounted) setState(() => _reservations = reservations);
    } catch (_) {
      if (mounted) setState(() => _reservations = const []);
    }
  }

  void _openComingSoon(BuildContext context, String title) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: title)),
    );
  }

  Future<void> _openReservarCancha(BuildContext context) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ReserveCourtsScreen(
          courtApiService: widget.courtApiService,
          sportApiService: widget.sportApiService,
        ),
      ),
    );
    _loadReservations();
  }

  Future<void> _openMyReservations(BuildContext context) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MyReservationsScreen(courtApiService: widget.courtApiService),
      ),
    );
    _loadReservations();
  }

  void _openMyCourts(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MyCourtsScreen(
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
  }

  void _openBuscarEquipos(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SearchTeamsScreen(
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
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
            userName: widget.userName,
            photoUrl: widget.photoUrl,
            location: 'Santa Cruz, Bolivia',
            onNotificationsTap: () => _showNoNotifications(context),
            onAvatarTap: widget.onOpenProfile,
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
                if (_reservations.isNotEmpty) ...[
                  const SizedBox(height: 28),
                  MyReservationsSection(
                    reservations: _reservations,
                    onTap: () => _openMyReservations(context),
                  ),
                ],
                SizedBox(height: _reservations.isEmpty ? 28 : 12),
                MyCourtsSection(onTap: () => _openMyCourts(context)),
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
