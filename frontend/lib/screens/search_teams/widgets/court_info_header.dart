import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../models/court_model.dart';

/// Photo carousel + address (tap to open Google Maps) for a court.
class CourtInfoHeader extends StatelessWidget {
  const CourtInfoHeader({required this.court, super.key});

  final CourtModel court;

  Future<void> _openInMaps(BuildContext context) async {
    final query = court.latitude != null && court.longitude != null
        ? '${court.latitude},${court.longitude}'
        : Uri.encodeComponent(court.address);
    final uri = Uri.parse('https://www.google.com/maps/search/?api=1&query=$query');

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(content: Text('No pudimos abrir Google Maps.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 200,
          child: court.photos.isEmpty
              ? Container(
                  color: colors.surfaceContainerHighest,
                  alignment: Alignment.center,
                  child: Icon(Icons.image_not_supported_outlined, color: colors.outline),
                )
              : court.photos.length == 1
                  ? Image.network(court.photos.first, fit: BoxFit.cover, width: double.infinity)
                  : PageView.builder(
                      itemCount: court.photos.length,
                      itemBuilder: (context, index) {
                        return Image.network(court.photos[index], fit: BoxFit.cover);
                      },
                    ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                court.name,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 6),
              InkWell(
                onTap: () => _openInMaps(context),
                child: Row(
                  children: [
                    Icon(Icons.location_on_outlined, size: 18, color: colors.primary),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        court.address,
                        style: TextStyle(
                          color: colors.primary,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (court.openingTime != null && court.closingTime != null) ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    Icon(Icons.access_time_rounded, size: 18, color: colors.outline),
                    const SizedBox(width: 6),
                    Text('${court.openingTime} - ${court.closingTime}'),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
