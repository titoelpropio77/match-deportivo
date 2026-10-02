import 'package:flutter/material.dart';

import '../../models/event_space_model.dart';
import '../../services/event_space_api_service.dart';
import 'event_space_card.dart';
import 'event_space_detail_screen.dart';
import 'my_events_screen.dart';

/// Grill areas, quinchos and halls of every sports center, filtered by group size.
class EventSpacesScreen extends StatefulWidget {
  const EventSpacesScreen({required this.eventSpaceApiService, super.key});

  final EventSpaceApiService eventSpaceApiService;

  @override
  State<EventSpacesScreen> createState() => _EventSpacesScreenState();
}

class _EventSpacesScreenState extends State<EventSpacesScreen> {
  /// Group sizes offered as filters (null = any).
  static const _groupSizes = <int?>[null, 15, 30, 50];

  int? _guests;
  List<EventSpaceModel> _spaces = const [];
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
      final spaces = await widget.eventSpaceApiService.list(guests: _guests);
      if (!mounted) return;
      setState(() {
        _spaces = spaces;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is EventSpaceApiException ? error.message : 'No pudimos cargar los espacios.';
        _loading = false;
      });
    }
  }

  void _open(EventSpaceModel space) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => EventSpaceDetailScreen(space: space, eventSpaceApiService: widget.eventSpaceApiService),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Espacios para eventos'),
        actions: [
          IconButton(
            tooltip: 'Mis eventos',
            icon: const Icon(Icons.event_note_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => MyEventsScreen(eventSpaceApiService: widget.eventSpaceApiService),
              ),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Text(
              'Parrilleros, quinchos y salones de los centros deportivos, '
              'por hora, para tu reunión, cumpleaños o asado.',
              style: textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 40,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _groupSizes.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final size = _groupSizes[index];
                  return ChoiceChip(
                    label: Text(size == null ? 'Cualquier tamaño' : '$size+ personas'),
                    selected: _guests == size,
                    onSelected: (_) {
                      setState(() => _guests = size);
                      _load();
                    },
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Column(
                children: [
                  const SizedBox(height: 32),
                  Text(_error!, textAlign: TextAlign.center),
                  TextButton(onPressed: _load, child: const Text('Reintentar')),
                ],
              )
            else if (_spaces.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 48),
                child: Column(
                  children: [
                    Icon(Icons.celebration_outlined, size: 48, color: colors.outline),
                    const SizedBox(height: 12),
                    Text(
                      _guests == null
                          ? 'Todavía no hay espacios para eventos.'
                          : 'No hay espacios para $_guests personas o más.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              )
            else
              for (final space in _spaces) ...[
                EventSpaceCard(space: space, onTap: () => _open(space)),
                const SizedBox(height: 16),
              ],
          ],
        ),
      ),
    );
  }
}
