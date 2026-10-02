import 'package:flutter/material.dart';

import '../../models/user_model.dart';
import '../../services/court_api_service.dart';
import '../../services/event_space_api_service.dart';
import '../event_spaces/my_events_screen.dart';
import '../../services/match_api_service.dart';
import '../../services/store_api_service.dart';
import '../../services/user_api_service.dart';
import '../my_courts/my_courts_screen.dart';
import '../my_reservations/my_reservations_screen.dart';
import '../stores/my_orders_screen.dart';
import 'player_profile_screen.dart';

/// Minimal "Perfil" tab: user info and session logout.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({
    required this.user,
    required this.onLogout,
    this.userApiService,
    this.matchApiService,
    this.courtApiService,
    super.key,
  });

  final UserModel user;
  final VoidCallback onLogout;

  /// Enables "Mi perfil de jugador" (stats, rating, editing).
  final UserApiService? userApiService;

  /// With both, the "Historial de reservas" and "Historial de canchas" entries are shown.
  final MatchApiService? matchApiService;
  final CourtApiService? courtApiService;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          CircleAvatar(
            radius: 40,
            backgroundColor: colors.primaryContainer,
            backgroundImage: user.photoUrl != null && user.photoUrl!.isNotEmpty
                ? NetworkImage(user.photoUrl!)
                : null,
            child: user.photoUrl != null && user.photoUrl!.isNotEmpty
                ? null
                : Text(
                    user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: colors.onPrimaryContainer,
                    ),
                  ),
          ),
          const SizedBox(height: 16),
          Center(
            child: Text(user.name, style: Theme.of(context).textTheme.titleLarge),
          ),
          const SizedBox(height: 4),
          Center(
            child: Text(user.email, style: Theme.of(context).textTheme.bodyMedium),
          ),
          if (user.phone != null && user.phone!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Center(
              child: Text(user.phone!, style: Theme.of(context).textTheme.bodyMedium),
            ),
          ],
          const SizedBox(height: 32),
          if (userApiService != null) ...[
            FilledButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => PlayerProfileScreen(
                    userId: user.id,
                    initialUser: user,
                    userApiService: userApiService!,
                  ),
                ),
              ),
              icon: const Icon(Icons.badge_outlined),
              label: const Text('Mi perfil de jugador'),
            ),
            const SizedBox(height: 12),
          ],
          if (matchApiService != null && courtApiService != null) ...[
            const SizedBox(height: 12),
            Text('Mi actividad', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Card(
              margin: EdgeInsets.zero,
              clipBehavior: Clip.antiAlias,
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.receipt_long_outlined),
                    title: const Text('Historial de reservas'),
                    subtitle: const Text('Canchas que reservaste, próximas y anteriores'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => MyReservationsScreen(
                          title: 'Historial de reservas',
                          courtApiService: courtApiService!,
                          matchApiService: matchApiService,
                          currentUserId: user.id,
                        ),
                      ),
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.celebration_outlined),
                    title: const Text('Mis eventos'),
                    subtitle: const Text('Parrilleros y salones que reservaste'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => MyEventsScreen(
                          eventSpaceApiService: EventSpaceApiService(
                            baseUrl: courtApiService!.baseUrl,
                            token: courtApiService!.token,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.stadium_outlined),
                    title: const Text('Historial de canchas'),
                    subtitle: const Text('Canchas (partidos) que creaste, activas y pasadas'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => MyCourtsScreen(
                          title: 'Historial de canchas',
                          matchApiService: matchApiService!,
                          currentUserId: user.id,
                        ),
                      ),
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.shopping_bag_outlined),
                    title: const Text('Mis compras'),
                    subtitle: const Text('Pedidos en las tiendas y su código de retiro'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => MyOrdersScreen(
                          storeApiService: StoreApiService(
                            baseUrl: courtApiService!.baseUrl,
                            token: courtApiService!.token,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
          ],
          OutlinedButton.icon(
            onPressed: onLogout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );
  }
}
