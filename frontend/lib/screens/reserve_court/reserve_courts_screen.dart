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
    super.key,
  });

  final CourtApiService courtApiService;
  final SportApiService sportApiService;

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

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _fields.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final field = _fields[index];
        final photo = field.venue.photos.isEmpty ? null : field.venue.photos.first;
        return Card(
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => CourtFieldDetailScreen(
                    field: field,
                    initialDate: _date,
                    courtApiService: widget.courtApiService,
                  ),
                ),
              );
            },
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (photo != null)
                  Image.network(
                    photo,
                    height: 140,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const SizedBox(
                      height: 140,
                      child: ColoredBox(
                        color: Colors.black12,
                        child: Center(child: Icon(Icons.image_not_supported_outlined)),
                      ),
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(field.venue.name, style: Theme.of(context).textTheme.titleMedium),
                      Text(field.name),
                      const SizedBox(height: 4),
                      Text(field.venue.address, style: Theme.of(context).textTheme.bodySmall),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        children: [
                          for (final sport in field.sports)
                            Chip(
                              label: Text(sport.name),
                              visualDensity: VisualDensity.compact,
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text('${formatBs(field.pricePerHour)} por hora'),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
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
