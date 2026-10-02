import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/sport_model.dart';
import '../../services/court_api_service.dart';
import '../../services/sport_api_service.dart';
import 'court_field_detail_screen.dart';

class ReserveCourtsScreen extends StatefulWidget {
  const ReserveCourtsScreen({
    required this.courtApiService,
    required this.sportApiService,
    this.returnAfterBooking = false,
    super.key,
  });

  /// Route name used to come back here (and close this screen) once a booking is paid.
  static const routeName = 'reserve-courts';

  final CourtApiService courtApiService;
  final SportApiService sportApiService;

  /// Opened from another flow (e.g. creating a match): after paying, pop this screen with `true`
  /// instead of going back to the home.
  final bool returnAfterBooking;

  @override
  State<ReserveCourtsScreen> createState() => _ReserveCourtsScreenState();
}

class _ReserveCourtsScreenState extends State<ReserveCourtsScreen> {
  List<CourtFieldModel> _fields = const [];
  List<SportModel> _sports = const [];
  int? _sportId;
  DateTime _date = DateUtils.dateOnly(DateTime.now());
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        widget.courtApiService.listFields(sportId: _sportId, date: _date),
        if (_sports.isEmpty) widget.sportApiService.list() else Future.value(_sports),
      ]);
      if (!mounted) return;
      setState(() {
        _fields = results[0] as List<CourtFieldModel>;
        _sports = results[1] as List<SportModel>;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is CourtApiException
            ? error.message
            : 'No pudimos cargar las canchas.';
        _loading = false;
      });
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateUtils.dateOnly(DateTime.now()),
      lastDate: DateTime.now().add(const Duration(days: 60)),
    );
    if (picked == null) return;
    setState(() => _date = DateUtils.dateOnly(picked));
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reservar cancha')),
      body: Column(
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: const Text('Todas'),
                    selected: _sportId == null,
                    onSelected: (_) {
                      setState(() => _sportId = null);
                      _load();
                    },
                  ),
                ),
                for (final sport in _sports)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(sport.name),
                      selected: _sportId == sport.id,
                      onSelected: (_) {
                        setState(() => _sportId = sport.id);
                        _load();
                      },
                    ),
                  ),
              ],
            ),
          ),
          ListTile(
            leading: const Icon(Icons.calendar_today_outlined),
            title: const Text('Fecha de disponibilidad'),
            subtitle: Text(_formatLongDate(_date)),
            trailing: const Icon(Icons.chevron_right),
            onTap: _pickDate,
          ),
          const Divider(height: 1),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Reintentar')),
          ],
        ),
      );
    }
    if (_fields.isEmpty) {
      return const Center(child: Text('No hay canchas libres para esa fecha.'));
    }

    final venues = _VenueGroup.fromFields(_fields);
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: venues.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final venue = venues[index];
        return _VenueCard(
          venue: venue,
          highlightedSportId: _sportId,
          onTap: () => _openVenue(venue),
        );
      },
    );
  }

  void _openVenue(_VenueGroup venue) {
    // Open on a court that offers the filtered sport, with that sport preselected.
    final sportId = _sportId;
    final field = sportId == null
        ? venue.fields.first
        : venue.fields.firstWhere(
            (field) => field.sports.any((sport) => sport.id == sportId),
            orElse: () => venue.fields.first,
          );
    final sport = sportId == null
        ? null
        : field.sports.where((sport) => sport.id == sportId).firstOrNull;

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CourtFieldDetailScreen(
          field: field,
          initialSport: sport,
          initialDate: _date,
          courtApiService: widget.courtApiService,
          returnAfterBooking: widget.returnAfterBooking,
        ),
      ),
    );
  }
}

/// The bookable courts of one sports center that matched the filters.
class _VenueGroup {
  _VenueGroup(this.venue, this.fields);

  final CourtVenueModel venue;
  final List<CourtFieldModel> fields;

  /// Every sport offered by any of its courts, in first-seen order.
  List<SportModel> get sports {
    final byId = <int, SportModel>{};
    for (final field in fields) {
      for (final sport in field.sports) {
        byId.putIfAbsent(sport.id, () => sport);
      }
    }
    return byId.values.toList();
  }

  double get minPrice => fields.map((field) => field.pricePerHour).reduce((a, b) => a < b ? a : b);
  double get maxPrice => fields.map((field) => field.pricePerHour).reduce((a, b) => a > b ? a : b);

  /// Keeps the API order (centers sorted by name).
  static List<_VenueGroup> fromFields(List<CourtFieldModel> fields) {
    final groups = <int, _VenueGroup>{};
    for (final field in fields) {
      groups.putIfAbsent(field.venue.id, () => _VenueGroup(field.venue, [])).fields.add(field);
    }
    return groups.values.toList();
  }
}

class _VenueCard extends StatelessWidget {
  const _VenueCard({required this.venue, required this.highlightedSportId, required this.onTap});

  final _VenueGroup venue;
  final int? highlightedSportId;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final photo = venue.venue.photos.isEmpty ? null : venue.venue.photos.first;
    final count = venue.fields.length;
    final price = venue.minPrice == venue.maxPrice
        ? '${formatBs(venue.minPrice)} por hora'
        : 'Desde ${formatBs(venue.minPrice)} por hora';
    final placeholder = SizedBox(
      height: 140,
      child: ColoredBox(
        color: colors.surfaceContainerHighest,
        child: Center(child: Icon(Icons.stadium_outlined, size: 40, color: colors.onSurfaceVariant)),
      ),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (photo == null)
              placeholder
            else
              Image.network(
                photo,
                height: 140,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => placeholder,
              ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(venue.venue.name, style: textTheme.titleMedium),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Icon(Icons.place_outlined, size: 14, color: colors.onSurfaceVariant),
                      const SizedBox(width: 4),
                      Expanded(child: Text(venue.venue.address, style: textTheme.bodySmall)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final sport in venue.sports)
                        Chip(
                          label: Text(sport.name),
                          visualDensity: VisualDensity.compact,
                          backgroundColor: sport.id == highlightedSportId ? colors.secondaryContainer : null,
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(Icons.grid_view_rounded, size: 16, color: colors.onSurfaceVariant),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          '$count ${count == 1 ? 'cancha disponible' : 'canchas disponibles'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: textTheme.bodySmall,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          price,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.end,
                          style: textTheme.titleSmall,
                        ),
                      ),
                    ],
                  ),
                  if ((venue.venue.eventSpacesCount ?? 0) > 0) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(Icons.celebration_outlined, size: 14, color: colors.tertiary),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            'También alquila espacios para eventos',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: textTheme.bodySmall?.copyWith(color: colors.tertiary),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String formatBs(double amount) {
  if (amount == amount.roundToDouble()) return 'Bs ${amount.toInt()}';
  return 'Bs ${amount.toStringAsFixed(2)}';
}

String _formatLongDate(DateTime date) {
  const months = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
  ];
  return '${date.day} de ${months[date.month - 1]} de ${date.year}';
}
