import 'package:flutter/material.dart';

import '../../models/user_model.dart';

/// Minimal "Perfil" tab: user info and session logout.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({required this.user, required this.onLogout, super.key});

  final UserModel user;
  final VoidCallback onLogout;

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
            child: Text(
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
