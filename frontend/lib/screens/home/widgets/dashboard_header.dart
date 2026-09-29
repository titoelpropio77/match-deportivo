import 'package:flutter/material.dart';

/// Top greeting banner showing the user's name, location, notifications and avatar.
class DashboardHeader extends StatelessWidget {
  const DashboardHeader({
    required this.userName,
    required this.location,
    required this.onNotificationsTap,
    required this.onAvatarTap,
    this.photoUrl,
    super.key,
  });

  final String userName;
  final String? photoUrl;
  final String location;
  final VoidCallback onNotificationsTap;
  final VoidCallback onAvatarTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 16, 12, 28),
      decoration: BoxDecoration(
        color: colors.primary,
        borderRadius: const BorderRadius.only(
          bottomLeft: Radius.circular(28),
          bottomRight: Radius.circular(28),
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Hola, $userName!',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: colors.onPrimary,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(
                        Icons.location_on,
                        size: 16,
                        color: colors.onPrimary.withValues(alpha: 0.85),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        location,
                        style: TextStyle(
                          color: colors.onPrimary.withValues(alpha: 0.85),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              onPressed: onNotificationsTap,
              icon: Icon(
                Icons.notifications_none_rounded,
                color: colors.onPrimary,
              ),
            ),
            GestureDetector(
              onTap: onAvatarTap,
              child: CircleAvatar(
                radius: 20,
                backgroundColor: colors.onPrimary,
                backgroundImage: photoUrl != null && photoUrl!.isNotEmpty
                    ? NetworkImage(photoUrl!)
                    : null,
                child: photoUrl != null && photoUrl!.isNotEmpty
                    ? null
                    : Text(
                        userName.isNotEmpty ? userName[0].toUpperCase() : '?',
                        style: TextStyle(
                          color: colors.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
