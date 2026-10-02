import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../models/team_model.dart';
import '../../models/user_model.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/team_api_service.dart';
import '../../services/user_api_service.dart';
import '../profile/player_profile_screen.dart';
import 'team_form_screen.dart';
import 'teams_screen.dart';
import 'widgets/team_badge.dart';

/// Team profile and roster. The captain edits it and manages members; a player can leave.
class TeamDetailScreen extends StatefulWidget {
  const TeamDetailScreen({
    required this.teamId,
    required this.teamApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.currentUserId,
    this.initialTeam,
    super.key,
  });

  final int teamId;
  final TeamModel? initialTeam;
  final TeamApiService teamApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final int currentUserId;

  @override
  State<TeamDetailScreen> createState() => _TeamDetailScreenState();
}

class _TeamDetailScreenState extends State<TeamDetailScreen> {
  late TeamModel? _team = widget.initialTeam;
  bool _loading = true;
  String? _error;

  bool get _isCaptain => _team?.ownerId == widget.currentUserId;
  bool get _isMember => _team?.members.any((member) => member.user.id == widget.currentUserId) ?? false;

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
      final team = await widget.teamApiService.show(widget.teamId);
      if (!mounted) return;
      setState(() {
        _team = team;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is TeamApiException ? error.message : 'No pudimos cargar el equipo.';
        _loading = false;
      });
    }
  }

  Future<void> _run(Future<TeamModel?> Function() action, {String? success}) async {
    try {
      final team = await action();
      if (!mounted) return;
      if (team != null) setState(() => _team = team);
      if (success != null) _snack(success);
    } catch (error) {
      _snack(error is TeamApiException ? error.message : 'No pudimos completar la acción.');
    }
  }

  Future<void> _edit() async {
    final updated = await Navigator.of(context).push<TeamModel>(
      MaterialPageRoute(
        builder: (_) => TeamFormScreen(
          team: _team,
          teamApiService: widget.teamApiService,
          userApiService: widget.userApiService,
          sportApiService: widget.sportApiService,
          matchLevelApiService: widget.matchLevelApiService,
          currentUserId: widget.currentUserId,
        ),
      ),
    );
    if (updated != null && mounted) {
      setState(() => _team = updated);
      _snack('Equipo actualizado.');
    }
  }

  Future<void> _addPlayer() async {
    final team = _team;
    if (team == null) return;
    final user = await showModalBottomSheet<UserModel>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _UserSearchSheet(
        userApiService: widget.userApiService,
        excludedIds: team.members.map((member) => member.user.id).toSet(),
      ),
    );
    if (user == null) return;
    await _run(() => widget.teamApiService.addMember(team.id, user.id), success: '${user.name} se unió al equipo.');
  }

  Future<void> _editMember(TeamMemberModel member) async {
    final result = await showDialog<({int? jersey, String? position})>(
      context: context,
      builder: (_) => _MemberDialog(member: member),
    );
    if (result == null) return;
    await _run(
      () => widget.teamApiService.updateMember(
        _team!.id,
        member.user.id,
        jerseyNumber: result.jersey,
        position: result.position,
      ),
    );
  }

  Future<void> _removeMember(TeamMemberModel member) async {
    final leaving = member.user.id == widget.currentUserId;
    final confirmed = await _confirm(
      title: leaving ? '¿Salir del equipo?' : '¿Quitar a ${member.user.name}?',
      message: leaving
          ? 'Dejarás de ver ${_team!.name} en tus equipos.'
          : '${member.user.name} dejará de ser parte de ${_team!.name}.',
      action: leaving ? 'Salir' : 'Quitar',
    );
    if (!confirmed) return;
    if (leaving) {
      try {
        await widget.teamApiService.removeMember(_team!.id, member.user.id);
        if (mounted) Navigator.of(context).pop();
      } catch (error) {
        _snack(error is TeamApiException ? error.message : 'No pudimos salir del equipo.');
      }
      return;
    }
    await _run(() => widget.teamApiService.removeMember(_team!.id, member.user.id));
  }

  Future<void> _delete() async {
    final confirmed = await _confirm(
      title: '¿Eliminar ${_team!.name}?',
      message: 'Se eliminará el equipo para todos sus jugadores. Los partidos ya creados no cambian.',
      action: 'Eliminar',
    );
    if (!confirmed) return;
    try {
      await widget.teamApiService.delete(_team!.id);
      if (mounted) Navigator.of(context).pop();
    } catch (error) {
      _snack(error is TeamApiException ? error.message : 'No pudimos eliminar el equipo.');
    }
  }

  Future<bool> _confirm({required String title, required String message, required String action}) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(action)),
        ],
      ),
    );
    return result == true;
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final team = _team;
    return Scaffold(
      appBar: AppBar(
        title: Text(team?.name ?? 'Equipo'),
        actions: [
          if (team != null && _isCaptain) ...[
            IconButton(tooltip: 'Editar equipo', onPressed: _edit, icon: const Icon(Icons.edit_outlined)),
            PopupMenuButton<String>(
              onSelected: (value) {
                if (value == 'delete') _delete();
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'delete', child: Text('Eliminar equipo')),
              ],
            ),
          ],
        ],
      ),
      floatingActionButton: team != null && _isCaptain
          ? FloatingActionButton.extended(
              onPressed: _addPlayer,
              icon: const Icon(Icons.person_add_alt_1_outlined),
              label: const Text('Agregar jugador'),
            )
          : null,
      body: _buildBody(team),
    );
  }

  Widget _buildBody(TeamModel? team) {
    if (team == null) {
      if (_loading) return const Center(child: CircularProgressIndicator());
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error ?? 'No encontramos el equipo.'),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Reintentar')),
          ],
        ),
      );
    }

    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final accent = parseTeamColor(team.primaryColor);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 96),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [accent.withValues(alpha: 0.25), colors.surface],
              ),
            ),
            child: Column(
              children: [
                TeamBadge(team: team, size: 96),
                const SizedBox(height: 12),
                Text(team.name, textAlign: TextAlign.center, style: textTheme.headlineSmall),
                if (team.shortName != null && team.shortName!.isNotEmpty)
                  Text(team.shortName!.toUpperCase(), style: textTheme.labelLarge?.copyWith(letterSpacing: 2)),
                const SizedBox(height: 12),
                Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (team.sport != null) _InfoChip(icon: Icons.sports_soccer_outlined, label: team.sport!.name),
                    _InfoChip(icon: Icons.wc_outlined, label: team.gender.label),
                    if (team.level != null) _InfoChip(icon: Icons.trending_up_rounded, label: team.level!.name),
                    _InfoChip(
                      icon: Icons.groups_outlined,
                      label: '${team.members.length} ${team.members.length == 1 ? 'jugador' : 'jugadores'}',
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (team.description != null && team.description!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: Text(team.description!, style: textTheme.bodyMedium),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Text('Plantel', style: textTheme.titleMedium),
          ),
          for (final member in team.members)
            _MemberTile(
              member: member,
              accent: accent,
              isMe: member.user.id == widget.currentUserId,
              canManage: _isCaptain && !member.isCaptain,
              canEdit: _isCaptain || member.user.id == widget.currentUserId,
              onEdit: () => _editMember(member),
              onRemove: () => _removeMember(member),
              onOpen: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => PlayerProfileScreen(
                    userId: member.user.id,
                    initialUser: member.user,
                    userApiService: widget.userApiService,
                  ),
                ),
              ),
            ),
          if (_isMember && !_isCaptain)
            Padding(
              padding: const EdgeInsets.all(16),
              child: OutlinedButton.icon(
                onPressed: () => _removeMember(team.members.firstWhere((member) => member.user.id == widget.currentUserId)),
                icon: const Icon(Icons.logout),
                label: const Text('Salir del equipo'),
              ),
            ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: Icon(icon, size: 16),
      label: Text(label),
      visualDensity: VisualDensity.compact,
    );
  }
}

class _MemberTile extends StatelessWidget {
  const _MemberTile({
    required this.member,
    required this.accent,
    required this.isMe,
    required this.canManage,
    required this.canEdit,
    required this.onEdit,
    required this.onRemove,
    required this.onOpen,
  });

  final TeamMemberModel member;
  final Color accent;
  final bool isMe;
  final bool canManage;
  final bool canEdit;
  final VoidCallback onEdit;
  final VoidCallback onRemove;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final foreground = accent.computeLuminance() > 0.5 ? Colors.black87 : Colors.white;
    final number = member.jerseyNumber;
    return ListTile(
      onTap: onOpen,
      leading: CircleAvatar(
        backgroundColor: accent,
        foregroundColor: foreground,
        backgroundImage: member.user.photoUrl == null || number != null ? null : NetworkImage(member.user.photoUrl!),
        child: number != null
            ? Text('$number', style: const TextStyle(fontWeight: FontWeight.w800))
            : (member.user.photoUrl == null ? Text(member.user.name.characters.first.toUpperCase()) : null),
      ),
      title: Row(
        children: [
          Flexible(child: Text(isMe ? '${member.user.name} (tú)' : member.user.name, overflow: TextOverflow.ellipsis)),
          if (member.isCaptain) ...[const SizedBox(width: 6), const CaptainTag()],
        ],
      ),
      subtitle: Text(
        [
          member.position,
          if (member.user.nickname != null && member.user.nickname!.isNotEmpty) '@${member.user.nickname}',
        ].whereType<String>().where((text) => text.isNotEmpty).join(' · ').ifEmpty('Jugador'),
      ),
      trailing: (canEdit || canManage)
          ? PopupMenuButton<String>(
              onSelected: (value) => value == 'edit' ? onEdit() : onRemove(),
              itemBuilder: (_) => [
                if (canEdit) const PopupMenuItem(value: 'edit', child: Text('Número y posición')),
                if (canManage) const PopupMenuItem(value: 'remove', child: Text('Quitar del equipo')),
              ],
            )
          : null,
    );
  }
}

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}

class _MemberDialog extends StatefulWidget {
  const _MemberDialog({required this.member});

  final TeamMemberModel member;

  @override
  State<_MemberDialog> createState() => _MemberDialogState();
}

class _MemberDialogState extends State<_MemberDialog> {
  late final _jersey = TextEditingController(text: widget.member.jerseyNumber?.toString() ?? '');
  late final _position = TextEditingController(text: widget.member.position ?? '');

  @override
  void dispose() {
    _jersey.dispose();
    _position.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.member.user.name),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _jersey,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(2)],
            decoration: const InputDecoration(labelText: 'Número de camiseta', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _position,
            maxLength: 40,
            textCapitalization: TextCapitalization.sentences,
            decoration: const InputDecoration(
              labelText: 'Posición',
              hintText: 'Ej.: Arquero, Delantero, Base',
              border: OutlineInputBorder(),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancelar')),
        FilledButton(
          onPressed: () => Navigator.of(context).pop((
            jersey: int.tryParse(_jersey.text.trim()),
            position: _position.text.trim().isEmpty ? null : _position.text.trim(),
          )),
          child: const Text('Guardar'),
        ),
      ],
    );
  }
}

/// Search app users to add to the team; pops the chosen [UserModel].
class _UserSearchSheet extends StatefulWidget {
  const _UserSearchSheet({required this.userApiService, required this.excludedIds});

  final UserApiService userApiService;
  final Set<int> excludedIds;

  @override
  State<_UserSearchSheet> createState() => _UserSearchSheetState();
}

class _UserSearchSheetState extends State<_UserSearchSheet> {
  List<UserModel> _results = const [];
  bool _searching = false;
  bool _searched = false;
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _onChanged(String query) {
    _debounce?.cancel();
    if (query.trim().isEmpty) {
      setState(() {
        _results = const [];
        _searched = false;
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () async {
      setState(() => _searching = true);
      try {
        final users = await widget.userApiService.search(query);
        if (!mounted) return;
        setState(() {
          _results = users.where((user) => !widget.excludedIds.contains(user.id)).toList();
          _searching = false;
          _searched = true;
        });
      } catch (_) {
        if (!mounted) return;
        setState(() {
          _results = const [];
          _searching = false;
          _searched = true;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.6,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                autofocus: true,
                autocorrect: false,
                decoration: InputDecoration(
                  labelText: 'Buscar jugador por nombre o email',
                  prefixIcon: const Icon(Icons.search),
                  border: const OutlineInputBorder(),
                  suffixIcon: _searching
                      ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                        )
                      : null,
                ),
                onChanged: _onChanged,
              ),
            ),
            Expanded(
              child: _searched && _results.isEmpty && !_searching
                  ? const Center(child: Text('No encontramos jugadores con ese nombre.'))
                  : ListView(
                      children: [
                        for (final user in _results)
                          ListTile(
                            leading: const Icon(Icons.person_add_alt_1_outlined),
                            title: Text(user.name),
                            subtitle: Text(user.email),
                            onTap: () => Navigator.of(context).pop(user),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
