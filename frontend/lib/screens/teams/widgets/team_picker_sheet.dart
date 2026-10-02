import 'dart:async';

import 'package:flutter/material.dart';

import '../../../models/team_model.dart';
import '../../../services/team_api_service.dart';
import 'team_badge.dart';

/// Bottom sheet to pick a team of [sportId]: the user's teams first, any team when searching.
/// Pops the chosen [TeamModel] with its members loaded.
class TeamPickerSheet extends StatefulWidget {
  const TeamPickerSheet({
    required this.teamApiService,
    required this.excludedIds,
    this.sportId,
    this.sportName,
    super.key,
  });

  final TeamApiService teamApiService;
  final Set<int> excludedIds;
  final int? sportId;
  final String? sportName;

  @override
  State<TeamPickerSheet> createState() => _TeamPickerSheetState();
}

class _TeamPickerSheetState extends State<TeamPickerSheet> {
  List<TeamModel> _teams = const [];
  bool _loading = true;
  bool _searchMode = false;
  int? _openingId;
  String? _error;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _loadMine();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadMine() async {
    setState(() {
      _loading = true;
      _searchMode = false;
      _error = null;
    });
    try {
      final teams = await widget.teamApiService.myTeams(sportId: widget.sportId);
      if (!mounted) return;
      setState(() {
        _teams = teams;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'No pudimos cargar tus equipos.';
        _loading = false;
      });
    }
  }

  void _onSearchChanged(String query) {
    _debounce?.cancel();
    if (query.trim().isEmpty) {
      _loadMine();
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 350), () async {
      setState(() {
        _loading = true;
        _searchMode = true;
        _error = null;
      });
      try {
        final teams = await widget.teamApiService.search(query, sportId: widget.sportId);
        if (!mounted) return;
        setState(() {
          _teams = teams;
          _loading = false;
        });
      } catch (_) {
        if (!mounted) return;
        setState(() {
          _error = 'No pudimos buscar equipos.';
          _loading = false;
        });
      }
    });
  }

  Future<void> _pick(TeamModel team) async {
    setState(() => _openingId = team.id);
    try {
      // The list has no roster; load it so every player can be added.
      final detail = await widget.teamApiService.show(team.id);
      if (mounted) Navigator.of(context).pop(detail);
    } catch (error) {
      if (!mounted) return;
      setState(() => _openingId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error is TeamApiException ? error.message : 'No pudimos cargar el equipo.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final visible = _teams.where((team) => !widget.excludedIds.contains(team.id)).toList();

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.7,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
              child: Text('Agregar equipo', style: textTheme.titleLarge),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Text(
                widget.sportName == null
                    ? 'Se agregarán todos sus jugadores al partido.'
                    : 'Equipos de ${widget.sportName}. Se agregarán todos sus jugadores al partido.',
                style: textTheme.bodySmall,
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                autocorrect: false,
                decoration: const InputDecoration(
                  labelText: 'Buscar cualquier equipo por nombre',
                  prefixIcon: Icon(Icons.search),
                  border: OutlineInputBorder(),
                ),
                onChanged: _onSearchChanged,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Text(_searchMode ? 'Resultados' : 'Mis equipos', style: textTheme.titleSmall),
            ),
            Expanded(child: _buildList(visible)),
          ],
        ),
      ),
    );
  }

  Widget _buildList(List<TeamModel> teams) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return Center(child: Text(_error!));
    if (teams.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          _searchMode
              ? 'No encontramos equipos con ese nombre.'
              : 'No tienes equipos${widget.sportName == null ? '' : ' de ${widget.sportName}'}. '
                  'Búscalos por nombre o créalos en "Equipos".',
          textAlign: TextAlign.center,
        ),
      );
    }
    return ListView.builder(
      itemCount: teams.length,
      itemBuilder: (context, index) {
        final team = teams[index];
        return ListTile(
          leading: TeamBadge(team: team, size: 44),
          title: Text(team.name),
          subtitle: Text(
            '${team.membersCount} ${team.membersCount == 1 ? 'jugador' : 'jugadores'}'
            '${team.sport == null ? '' : ' · ${team.sport!.name}'}',
          ),
          trailing: _openingId == team.id
              ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.add_circle_outline),
          onTap: _openingId == null ? () => _pick(team) : null,
        );
      },
    );
  }
}
