import 'package:flutter/material.dart';

import '../../models/ranking_model.dart';
import '../../services/ranking_api_service.dart';

/// Leaderboards: sports centers, teams, players, products and stores, for all time or this month.
class RankingScreen extends StatefulWidget {
  const RankingScreen({
    required this.rankingApiService,
    this.onCourtPressed,
    super.key,
  });

  final RankingApiService rankingApiService;

  /// Opens a sports center (e.g. its booking screen). Centers are not tappable when null.
  final void Function(RankingEntry court)? onCourtPressed;

  @override
  State<RankingScreen> createState() => _RankingScreenState();
}

class _RankingScreenState extends State<RankingScreen> {
  RankingPeriod _period = RankingPeriod.all;
  RankingModel? _ranking;
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
      final ranking = await widget.rankingApiService.fetch(period: _period);
      if (!mounted) return;
      setState(() {
        _ranking = ranking;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'No pudimos cargar el ranking.';
        _loading = false;
      });
    }
  }

  void _changePeriod(RankingPeriod period) {
    if (period == _period) return;
    setState(() => _period = period);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _RankingCategory.values.length,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Ranking'),
          bottom: TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              for (final category in _RankingCategory.values)
                Tab(icon: Icon(category.icon), text: category.label),
            ],
          ),
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: SizedBox(
                width: double.infinity,
                child: SegmentedButton<RankingPeriod>(
                  segments: [
                    for (final period in RankingPeriod.values)
                      ButtonSegment(value: period, label: Text(period.label)),
                  ],
                  selected: {_period},
                  onSelectionChanged: (selection) =>
                      _changePeriod(selection.first),
                ),
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null || _ranking == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 40),
            const SizedBox(height: 8),
            Text(_error ?? 'No pudimos cargar el ranking.'),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Reintentar'),
            ),
          ],
        ),
      );
    }

    final ranking = _ranking!;
    return TabBarView(
      children: [
        for (final category in _RankingCategory.values)
          RefreshIndicator(
            onRefresh: _load,
            child: _RankingList(
              category: category,
              entries: category.entriesOf(ranking),
              period: _period,
              onTap: category == _RankingCategory.courts
                  ? widget.onCourtPressed
                  : null,
            ),
          ),
      ],
    );
  }
}

enum _RankingCategory {
  courts('Centros', Icons.stadium_outlined),
  teams('Equipos', Icons.groups_outlined),
  players('Jugadores', Icons.person_outline),
  products('Productos', Icons.shopping_bag_outlined),
  stores('Tiendas', Icons.storefront_outlined);

  const _RankingCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  List<RankingEntry> entriesOf(RankingModel ranking) => switch (this) {
    courts => ranking.courts,
    teams => ranking.teams,
    players => ranking.players,
    products => ranking.products,
    stores => ranking.stores,
  };

  /// How the list is ordered, shown above it.
  String get criteria => switch (this) {
    courts => 'Por reservas concretadas y calificación',
    teams => 'Por puntos en torneos (3 por victoria, 1 por empate)',
    players => 'Por partidos jugados (partidos y torneos)',
    products => 'Por unidades vendidas en las tiendas',
    stores => 'Por cantidad de ventas',
  };

  String emptyText(RankingPeriod period) {
    final when = period == RankingPeriod.month ? ' este mes' : ' todavía';
    return switch (this) {
      courts => 'No hay centros deportivos registrados.',
      teams => 'Ningún equipo jugó partidos de torneo$when.',
      players => 'Nadie terminó partidos$when.',
      products => 'No se vendieron productos$when.',
      stores => 'Ninguna tienda registró ventas$when.',
    };
  }
}

class _RankingList extends StatelessWidget {
  const _RankingList({
    required this.category,
    required this.entries,
    required this.period,
    this.onTap,
  });

  final _RankingCategory category;
  final List<RankingEntry> entries;
  final RankingPeriod period;
  final void Function(RankingEntry entry)? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (entries.isEmpty) {
      // Scrollable so pull-to-refresh still works.
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Icon(category.icon, size: 48, color: theme.colorScheme.outline),
          const SizedBox(height: 12),
          Text(category.emptyText(period), textAlign: TextAlign.center),
        ],
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: entries.length + 1,
      separatorBuilder: (_, index) =>
          index == 0 ? const SizedBox(height: 4) : const Divider(height: 1),
      itemBuilder: (context, index) {
        if (index == 0) {
          return Text(category.criteria, style: theme.textTheme.bodySmall);
        }
        final entry = entries[index - 1];
        return _RankingTile(
          position: index,
          entry: entry,
          category: category,
          onTap: onTap == null ? null : () => onTap!(entry),
        );
      },
    );
  }
}

class _RankingTile extends StatelessWidget {
  const _RankingTile({
    required this.position,
    required this.entry,
    required this.category,
    this.onTap,
  });

  final int position;
  final RankingEntry entry;
  final _RankingCategory category;
  final VoidCallback? onTap;

  static const _medals = [
    Color(0xFFFFB300),
    Color(0xFF9E9E9E),
    Color(0xFFB87333),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final (value, unit) = _mainMetric();
    final details = _details();

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 4),
      onTap: onTap,
      leading: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 28,
            child: position <= 3
                ? Icon(Icons.emoji_events, color: _medals[position - 1])
                : Text(
                    '$position',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          ),
          const SizedBox(width: 8),
          _Avatar(entry: entry, icon: category.icon),
        ],
      ),
      title: Text(entry.name, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        [
          if (entry.subtitle != null && entry.subtitle!.isNotEmpty)
            entry.subtitle!,
          if (details.isNotEmpty) details,
        ].join('\n'),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      isThreeLine:
          details.isNotEmpty &&
          entry.subtitle != null &&
          entry.subtitle!.isNotEmpty,
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
              color: theme.colorScheme.primary,
            ),
          ),
          Text(unit, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }

  (String, String) _mainMetric() => switch (category) {
    _RankingCategory.courts => (
      '${entry.bookings}',
      entry.bookings == 1 ? 'reserva' : 'reservas',
    ),
    _RankingCategory.teams => ('${entry.points}', 'pts'),
    _RankingCategory.players => (
      '${entry.matchesPlayed}',
      entry.matchesPlayed == 1 ? 'partido' : 'partidos',
    ),
    _RankingCategory.products => (
      '${entry.unitsSold}',
      entry.unitsSold == 1 ? 'vendido' : 'vendidos',
    ),
    _RankingCategory.stores => (
      '${entry.orders}',
      entry.orders == 1 ? 'venta' : 'ventas',
    ),
  };

  String _details() {
    final rating = entry.rating == null
        ? null
        : '★ ${entry.rating!.toStringAsFixed(1)}${entry.reviewsCount > 0 ? ' (${entry.reviewsCount})' : ''}';
    final goalDifference = entry.goalDifference > 0
        ? '+${entry.goalDifference}'
        : '${entry.goalDifference}';

    return switch (category) {
      _RankingCategory.courts => rating ?? 'Sin reseñas',
      _RankingCategory.teams =>
        'PJ ${entry.played} · ${entry.won}G ${entry.drawn}E ${entry.lost}P · DG $goalDifference',
      _RankingCategory.players => rating ?? '',
      _RankingCategory.products =>
        entry.price == null ? '' : 'Bs ${entry.price!.toStringAsFixed(0)}',
      _RankingCategory.stores =>
        '${entry.unitsSold} ${entry.unitsSold == 1 ? 'producto' : 'productos'}',
    };
  }
}

/// Photo when available; otherwise initials (or the team abbreviation) on a coloured circle.
class _Avatar extends StatelessWidget {
  const _Avatar({required this.entry, required this.icon});

  final RankingEntry entry;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final background = _parseColor(entry.color) ?? colors.primaryContainer;
    final foreground = entry.color != null
        ? Colors.white
        : colors.onPrimaryContainer;
    final label = (entry.shortName?.isNotEmpty ?? false)
        ? entry.shortName!
        : entry.name.trim().isEmpty
        ? '?'
        : entry.name.trim()[0].toUpperCase();

    final fallback = CircleAvatar(
      radius: 22,
      backgroundColor: background,
      child: Text(
        label,
        style: TextStyle(
          color: foreground,
          fontWeight: FontWeight.w700,
          fontSize: label.length > 2 ? 11 : 16,
        ),
      ),
    );

    if (entry.imageUrl == null || entry.imageUrl!.isEmpty) return fallback;

    return ClipOval(
      child: SizedBox.square(
        dimension: 44,
        child: Image.network(
          entry.imageUrl!,
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => fallback,
          loadingBuilder: (context, child, progress) =>
              progress == null ? child : fallback,
        ),
      ),
    );
  }

  static Color? _parseColor(String? hex) {
    final match = RegExp(r'^#?([0-9a-fA-F]{6})$').firstMatch(hex?.trim() ?? '');
    return match == null
        ? null
        : Color(int.parse('FF${match.group(1)}', radix: 16));
  }
}
