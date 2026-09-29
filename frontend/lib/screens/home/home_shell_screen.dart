import 'package:flutter/material.dart';

import '../../models/user_model.dart';
import '../../services/court_api_service.dart';
import '../../services/match_api_service.dart';
import '../../services/sport_api_service.dart';
import '../placeholder/coming_soon_screen.dart';
import '../profile/profile_screen.dart';
import 'dashboard_screen.dart';

/// Root screen shown after login: bottom navigation with Inicio, Explorar and Perfil.
class HomeShellScreen extends StatefulWidget {
  const HomeShellScreen({
    required this.user,
    required this.matchApiService,
    required this.onLogout,
    super.key,
  });

  final UserModel user;
  final MatchApiService matchApiService;
  final VoidCallback onLogout;

  @override
  State<HomeShellScreen> createState() => _HomeShellScreenState();
}

class _HomeShellScreenState extends State<HomeShellScreen> {
  int _currentIndex = 0;
  late final _sportApiService = SportApiService(
    baseUrl: widget.matchApiService.baseUrl,
    token: widget.matchApiService.token,
  );
  late final _courtApiService = CourtApiService(
    baseUrl: widget.matchApiService.baseUrl,
    token: widget.matchApiService.token,
  );

  @override
  void dispose() {
    _sportApiService.dispose();
    _courtApiService.dispose();
    super.dispose();
  }

  void _goToProfileTab() => setState(() => _currentIndex = 2);

  @override
  Widget build(BuildContext context) {
    final tabs = [
      DashboardScreen(
        userName: widget.user.name,
        photoUrl: widget.user.photoUrl,
        currentUserId: widget.user.id,
        matchApiService: widget.matchApiService,
        sportApiService: _sportApiService,
        courtApiService: _courtApiService,
        onOpenProfile: _goToProfileTab,
      ),
      const ComingSoonScreen(title: 'Explorar'),
      ProfileScreen(user: widget.user, onLogout: widget.onLogout),
    ];

    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: tabs),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) => setState(() => _currentIndex = index),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Inicio'),
          NavigationDestination(icon: Icon(Icons.explore_outlined), label: 'Explorar'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Perfil'),
        ],
      ),
    );
  }
}
