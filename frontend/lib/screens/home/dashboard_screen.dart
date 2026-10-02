import 'package:flutter/material.dart';

import '../../data/dummy_featured_courts.dart';
import '../../models/court_field_model.dart';
import '../../models/event_space_model.dart';
import '../../models/featured_court_model.dart';
import '../../services/court_api_service.dart';
import '../../services/event_space_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/store_api_service.dart';
import '../../services/team_api_service.dart';
import '../../services/tournament_api_service.dart';
import '../../services/user_api_service.dart';
import '../event_spaces/event_spaces_screen.dart';
import '../my_courts/my_courts_screen.dart';
import '../my_reservations/my_reservations_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import '../placeholder/coming_soon_screen.dart';
import '../search_teams/search_teams_screen.dart';
import '../stores/stores_screen.dart';
import '../teams/teams_screen.dart';
import '../tournaments/tournaments_screen.dart';
import 'widgets/dashboard_header.dart';
import 'widgets/event_spaces_invite.dart';
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
  List<EventSpaceModel> _eventSpaces = const [];
  late final EventSpaceApiService _eventSpaceApiService = EventSpaceApiService(
    baseUrl: widget.courtApiService.baseUrl,
    token: widget.courtApiService.token,
  );

  @override
  void initState() {
    super.initState();
    _loadReservations();
    _loadEventSpaces();
  }

  /// The event spaces invitation only appears when some center rents them; failures just hide it.
  Future<void> _loadEventSpaces() async {
    try {
      final spaces = await _eventSpaceApiService.list();
      if (mounted) setState(() => _eventSpaces = spaces);
    } catch (_) {
      if (mounted) setState(() => _eventSpaces = const []);
    }
  }

  void _openEventSpaces(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => EventSpacesScreen(eventSpaceApiService: _eventSpaceApiService),
      ),
    );
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

  void _openTournaments(BuildContext context) {
    final baseUrl = widget.matchApiService.baseUrl;
    final token = widget.matchApiService.token;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TournamentsScreen(
          tournamentApiService: TournamentApiService(baseUrl: baseUrl, token: token),
          teamApiService: TeamApiService(baseUrl: baseUrl, token: token),
          currentUserId: widget.currentUserId,
        ),
      ),
    );
  }

  void _openTeams(BuildContext context) {
    final baseUrl = widget.matchApiService.baseUrl;
    final token = widget.matchApiService.token;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TeamsScreen(
          teamApiService: TeamApiService(baseUrl: baseUrl, token: token),
          userApiService: UserApiService(baseUrl: baseUrl, token: token),
          sportApiService: widget.sportApiService,
          matchLevelApiService: MatchLevelApiService(baseUrl: baseUrl, token: token),
          currentUserId: widget.currentUserId,
        ),
      ),
    );
  }

  Future<void> _openMyReservations(BuildContext context) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MyReservationsScreen(
          courtApiService: widget.courtApiService,
          matchApiService: widget.matchApiService,
          currentUserId: widget.currentUserId,
        ),
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

  void _openStores(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => StoresScreen(
          storeApiService: StoreApiService(
            baseUrl: widget.courtApiService.baseUrl,
            token: widget.courtApiService.token,
          ),
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
                      onTap: () => _openTournaments(context),
                    ),
                    QuickActionData(
                      icon: Icons.groups_outlined,
                      label: 'Equipos',
                      onTap: () => _openTeams(context),
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
                      icon: Icons.stadium_outlined,
                      label: 'Buscar/Crear\nCanchas',
                      onTap: () => _openBuscarEquipos(context),
                    ),
                    QuickActionData(
                      icon: Icons.storefront_outlined,
                      label: 'Tiendas',
                      onTap: () => _openStores(context),
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
                if (_eventSpaces.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  EventSpacesInvite(
                    spaces: _eventSpaces,
                    onTap: () => _openEventSpaces(context),
                  ),
                ],
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
