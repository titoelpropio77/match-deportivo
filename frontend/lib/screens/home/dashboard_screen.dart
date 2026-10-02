import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/banner_model.dart';

import '../../models/court_field_model.dart';
import '../../models/event_space_model.dart';
import '../../models/featured_court_model.dart';
import '../../services/banner_api_service.dart';
import '../../services/court_api_service.dart';
import '../../services/ranking_api_service.dart';
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
import '../ranking/ranking_screen.dart';
import '../reserve_court/court_field_detail_screen.dart';
import '../reserve_court/reserve_courts_screen.dart';
import '../placeholder/coming_soon_screen.dart';
import '../search_teams/search_teams_screen.dart';
import '../stores/stores_screen.dart';
import '../teams/teams_screen.dart';
import '../tournaments/tournament_detail_screen.dart';
import '../tournaments/tournaments_screen.dart';
import 'widgets/banner_carousel.dart';
import 'widgets/dashboard_header.dart';
import 'widgets/event_spaces_invite.dart';
import 'widgets/featured_courts_section.dart';
import 'widgets/my_courts_section.dart';
import 'widgets/my_reservations_section.dart';
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
  /// Shown until the server banners arrive, and when there are none.
  static const _defaultBanners = [
    BannerModel(
      id: 0,
      title: '¡Reserva tu cancha hoy!',
      subtitle: 'Elige horario y paga con QR en segundos.',
      buttonLabel: 'Reservar ahora',
      link: BannerLink(type: BannerLinkType.reserveCourts),
    ),
  ];

  List<BannerModel> _banners = _defaultBanners;
  late final BannerApiService _bannerApiService =
      BannerApiService(baseUrl: widget.courtApiService.baseUrl);
  List<CourtReservationModel> _reservations = const [];
  List<EventSpaceModel> _eventSpaces = const [];
  List<FeaturedCourtModel> _featured = const [];
  bool _featuredLoading = true;
  String? _featuredError;
  late final EventSpaceApiService _eventSpaceApiService = EventSpaceApiService(
    baseUrl: widget.courtApiService.baseUrl,
    token: widget.courtApiService.token,
  );

  @override
  void initState() {
    super.initState();
    _loadReservations();
    _loadEventSpaces();
    _loadFeatured();
    _loadBanners();
  }

  /// Failures keep the default banner, so the carousel never disappears.
  Future<void> _loadBanners() async {
    try {
      final banners = await _bannerApiService.list();
      if (mounted) setState(() => _banners = banners.isEmpty ? _defaultBanners : banners);
    } catch (_) {
      if (mounted) setState(() => _banners = _defaultBanners);
    }
  }

  /// Opens the app section, record or external page a banner points to.
  Future<void> _openBanner(BuildContext context, BannerModel banner) async {
    final link = banner.link;
    switch (link.type) {
      case BannerLinkType.none:
        return;
      case BannerLinkType.reserveCourts:
        await _openReservarCancha(context);
      case BannerLinkType.court:
        if (link.id != null) await _openCourt(context, link.id!, banner.title);
      case BannerLinkType.tournaments:
        _openTournaments(context);
      case BannerLinkType.tournament:
        if (link.id != null) _openTournament(context, link.id!);
      case BannerLinkType.teams:
        _openTeams(context);
      case BannerLinkType.stores:
        _openStores(context);
      case BannerLinkType.eventSpaces:
        _openEventSpaces(context);
      case BannerLinkType.url:
        await _openExternalUrl(context, link.url);
    }
  }

  void _openRanking(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (rankingContext) => RankingScreen(
          rankingApiService: RankingApiService(baseUrl: widget.courtApiService.baseUrl),
          onCourtPressed: (court) => _openCourt(rankingContext, court.id, court.name),
        ),
      ),
    );
  }

  void _openTournament(BuildContext context, int tournamentId) {
    final baseUrl = widget.matchApiService.baseUrl;
    final token = widget.matchApiService.token;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TournamentDetailScreen(
          tournamentId: tournamentId,
          tournamentApiService: TournamentApiService(baseUrl: baseUrl, token: token),
          teamApiService: TeamApiService(baseUrl: baseUrl, token: token),
          currentUserId: widget.currentUserId,
        ),
      ),
    );
  }

  Future<void> _openExternalUrl(BuildContext context, String? url) async {
    final messenger = ScaffoldMessenger.of(context);
    final uri = url == null ? null : Uri.tryParse(url);
    final opened = uri != null && await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened) {
      messenger.showSnackBar(const SnackBar(content: Text('No pudimos abrir el enlace.')));
    }
  }

  Future<void> _loadFeatured() async {
    setState(() {
      _featuredLoading = true;
      _featuredError = null;
    });
    try {
      final courts = await widget.courtApiService.featured();
      if (!mounted) return;
      setState(() {
        _featured = courts;
        _featuredLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _featuredError = 'No pudimos cargar los complejos destacados.';
        _featuredLoading = false;
      });
    }
  }

  /// Opens the booking screen on the first court of a sports center.
  Future<void> _openCourt(BuildContext context, int courtId, String name) async {
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final today = DateUtils.dateOnly(DateTime.now());

    try {
      final fields = (await widget.courtApiService.listFields())
          .where((field) => field.venue.id == courtId)
          .toList();
      if (!mounted) return;
      if (fields.isEmpty) {
        messenger.showSnackBar(
          SnackBar(content: Text('$name todavía no tiene canchas para reservar.')),
        );
        return;
      }

      await navigator.push(
        MaterialPageRoute(
          builder: (_) => CourtFieldDetailScreen(
            field: fields.first,
            initialDate: today,
            courtApiService: widget.courtApiService,
          ),
        ),
      );
      _loadReservations();
    } catch (_) {
      if (!mounted) return;
      messenger.showSnackBar(
        const SnackBar(content: Text('No pudimos abrir el complejo. Intenta de nuevo.')),
      );
    }
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
                BannerCarousel(
                  banners: _banners,
                  onBannerPressed: (banner) => _openBanner(context, banner),
                ),
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
                      onTap: () => _openRanking(context),
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
                  courts: _featured,
                  loading: _featuredLoading,
                  error: _featuredError,
                  onRetry: _loadFeatured,
                  onCourtPressed: (court) => _openCourt(context, court.id, court.name),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
