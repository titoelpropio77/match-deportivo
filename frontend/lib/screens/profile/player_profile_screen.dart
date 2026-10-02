import 'package:flutter/material.dart';

import '../../models/player_profile_model.dart';
import '../../models/user_model.dart';
import '../../services/sport_api_service.dart';
import '../../services/user_api_service.dart';
import '../teams/widgets/team_badge.dart';
import 'favorite_sports_picker.dart';

const _genderLabels = {'male': 'Masculino', 'female': 'Femenino'};

/// Player profile opened by tapping a player: summary, stats, rating, tags, teams and last matches.
/// On your own profile it also lets you edit your details.
class PlayerProfileScreen extends StatefulWidget {
  const PlayerProfileScreen({
    required this.userId,
    required this.userApiService,
    this.initialUser,
    super.key,
  });

  final int userId;
  final UserApiService userApiService;

  /// Shown in the header while the full profile loads.
  final UserModel? initialUser;

  @override
  State<PlayerProfileScreen> createState() => _PlayerProfileScreenState();
}

class _PlayerProfileScreenState extends State<PlayerProfileScreen> {
  PlayerProfileModel? _profile;
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
      final profile = await widget.userApiService.profile(widget.userId);
      if (!mounted) return;
      setState(() {
        _profile = profile;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is UserApiException ? error.message : 'No pudimos cargar el perfil.';
        _loading = false;
      });
    }
  }

  Future<void> _edit() async {
    final profile = _profile;
    if (profile == null) return;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => EditProfileSheet(user: profile.user, userApiService: widget.userApiService),
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Perfil actualizado.')));
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final profile = _profile;
    return Scaffold(
      appBar: AppBar(
        title: Text(profile?.isMe == true ? 'Mi perfil de jugador' : 'Perfil del jugador'),
        actions: [
          if (profile?.isMe == true)
            IconButton(tooltip: 'Editar perfil', onPressed: _edit, icon: const Icon(Icons.edit_outlined)),
        ],
      ),
      body: _buildBody(profile),
    );
  }

  Widget _buildBody(PlayerProfileModel? profile) {
    if (profile == null) {
      if (_loading) {
        return Column(
          children: [
            if (widget.initialUser != null) _Header(user: widget.initialUser!, age: null),
            const Expanded(child: Center(child: CircularProgressIndicator())),
          ],
        );
      }
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error ?? 'No encontramos al jugador.'),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Reintentar')),
          ],
        ),
      );
    }

    final missing = profile.isMe &&
        (profile.user.birthDate == null ||
            (profile.user.nickname ?? '').isEmpty ||
            (profile.user.preferredPosition ?? '').isEmpty ||
            profile.user.favoriteSports.isEmpty);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          _Header(user: profile.user, age: profile.age),
          if (missing)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Card(
                color: Theme.of(context).colorScheme.secondaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.badge_outlined),
                  title: const Text('Completa tu perfil'),
                  subtitle: const Text('Agrega tu apodo, posición, fecha de nacimiento y deportes favoritos para que te conozcan.'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: _edit,
                ),
              ),
            ),
          if (profile.user.favoriteSports.isNotEmpty)
            _Section(
              title: 'Deportes favoritos',
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final sport in profile.user.favoriteSports)
                    Chip(
                      avatar: const Icon(Icons.favorite_rounded, size: 16, color: Colors.redAccent),
                      label: Text(sport.name),
                    ),
                ],
              ),
            ),
          _Section(
            title: 'Calificación',
            child: _RatingCard(rating: profile.rating),
          ),
          _Section(
            title: 'Estadísticas',
            child: _StatsGrid(stats: profile.stats),
          ),
          if (profile.topTags.isNotEmpty)
            _Section(
              title: 'Lo que destacan sus compañeros',
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [for (final tag in profile.topTags) _TagChip(label: tag.label, positive: tag.positive, count: tag.count)],
              ),
            ),
          if (profile.stats.sports.isNotEmpty)
            _Section(
              title: 'Deportes que juega',
              child: _SportsBars(sports: profile.stats.sports),
            ),
          if (profile.teams.isNotEmpty)
            _Section(
              title: 'Equipos',
              child: Column(
                children: [
                  for (final team in profile.teams)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: TeamBadgeView(
                        initials: _teamInitials(team),
                        color: team.primaryColor,
                        logoUrl: team.logoUrl,
                        size: 40,
                      ),
                      title: Text(team.name),
                      subtitle: Text(
                        [
                          team.sportName,
                          '${team.membersCount} ${team.membersCount == 1 ? 'jugador' : 'jugadores'}',
                          if (team.isCaptain) 'Capitán',
                        ].whereType<String>().join(' · '),
                      ),
                    ),
                ],
              ),
            ),
          if (profile.recentMatches.isNotEmpty)
            _Section(
              title: 'Últimos partidos',
              child: Column(
                children: [
                  for (final match in profile.recentMatches)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const CircleAvatar(child: Icon(Icons.sports_score_outlined, size: 20)),
                      title: Text([match.sport, match.court].whereType<String>().join(' · ')),
                      subtitle: Text(_shortDate(match.startTime)),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

String _teamInitials(PlayerTeam team) {
  final short = team.shortName?.trim();
  if (short != null && short.isNotEmpty) return short.toUpperCase();
  final words = team.name.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
  if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
  return words.isEmpty ? '?' : words.first.substring(0, words.first.length.clamp(1, 2)).toUpperCase();
}

const _months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

String _shortDate(DateTime date) => '${date.day} ${_months[date.month - 1]} ${date.year}';

class _Header extends StatelessWidget {
  const _Header({required this.user, required this.age});

  final UserModel user;
  final int? age;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final nickname = user.nickname?.trim();
    final title = nickname != null && nickname.isNotEmpty ? nickname : user.name;
    final photo = user.photoUrl;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [colors.primaryContainer, colors.surface],
        ),
      ),
      child: Column(
        children: [
          CircleAvatar(
            radius: 48,
            backgroundColor: colors.primary,
            foregroundColor: colors.onPrimary,
            backgroundImage: photo != null && photo.isNotEmpty ? NetworkImage(photo) : null,
            child: photo != null && photo.isNotEmpty
                ? null
                : Text(
                    title.isNotEmpty ? title.characters.first.toUpperCase() : '?',
                    style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w700),
                  ),
          ),
          const SizedBox(height: 12),
          Text(title, textAlign: TextAlign.center, style: textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
          if (nickname != null && nickname.isNotEmpty)
            Text(user.name, textAlign: TextAlign.center, style: textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant)),
          const SizedBox(height: 12),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              if (age != null) _HeaderChip(icon: Icons.cake_outlined, label: '$age años'),
              if (_genderLabels[user.gender] != null) _HeaderChip(icon: Icons.wc_outlined, label: _genderLabels[user.gender]!),
              if ((user.preferredPosition ?? '').isNotEmpty)
                _HeaderChip(icon: Icons.sports_handball_outlined, label: user.preferredPosition!),
              if (user.createdAt != null)
                _HeaderChip(
                  icon: Icons.event_outlined,
                  label: 'Desde ${_months[user.createdAt!.month - 1]} ${user.createdAt!.year}',
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeaderChip extends StatelessWidget {
  const _HeaderChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Chip(avatar: Icon(icon, size: 16), label: Text(label), visualDensity: VisualDensity.compact);
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          child,
        ],
      ),
    );
  }
}

class _RatingCard extends StatelessWidget {
  const _RatingCard({required this.rating});

  final PlayerRatingSummary rating;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final average = rating.average;

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: average == null
            ? Row(
                children: [
                  Icon(Icons.star_border_rounded, size: 40, color: colors.onSurfaceVariant),
                  const SizedBox(width: 12),
                  const Expanded(child: Text('Todavía no tiene calificaciones. Aparecerán cuando juegue y lo califiquen.')),
                ],
              )
            : Column(
                children: [
                  Row(
                    children: [
                      Column(
                        children: [
                          Text(average.toStringAsFixed(1), style: textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w800)),
                          _Stars(value: average),
                          const SizedBox(height: 4),
                          Text(
                            '${rating.count} ${rating.count == 1 ? 'calificación' : 'calificaciones'}',
                            style: textTheme.bodySmall,
                          ),
                        ],
                      ),
                      const SizedBox(width: 20),
                      Expanded(
                        child: Column(
                          children: [
                            for (final stars in [5, 4, 3, 2, 1])
                              _DistributionBar(
                                stars: stars,
                                count: rating.distribution[stars] ?? 0,
                                total: rating.count,
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (rating.attendanceRate != null) ...[
                    const Divider(height: 24),
                    Row(
                      children: [
                        Icon(
                          Icons.verified_outlined,
                          size: 20,
                          color: rating.attendanceRate! >= 80 ? Colors.green.shade700 : Colors.orange.shade800,
                        ),
                        const SizedBox(width: 8),
                        Expanded(child: Text('Asistencia ${rating.attendanceRate}%')),
                        Text(
                          rating.noShows == 0
                              ? 'Nunca faltó'
                              : '${rating.noShows} ${rating.noShows == 1 ? 'falta' : 'faltas'}',
                          style: textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ],
                ],
              ),
      ),
    );
  }
}

class _Stars extends StatelessWidget {
  const _Stars({required this.value});

  final double value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 1; i <= 5; i++)
          Icon(
            value >= i
                ? Icons.star_rounded
                : value >= i - 0.5
                    ? Icons.star_half_rounded
                    : Icons.star_border_rounded,
            size: 18,
            color: Colors.amber.shade700,
          ),
      ],
    );
  }
}

class _DistributionBar extends StatelessWidget {
  const _DistributionBar({required this.stars, required this.count, required this.total});

  final int stars;
  final int count;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          SizedBox(width: 12, child: Text('$stars', style: Theme.of(context).textTheme.bodySmall)),
          const SizedBox(width: 6),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: total == 0 ? 0 : count / total,
                minHeight: 8,
                color: Colors.amber.shade700,
                backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
              ),
            ),
          ),
          const SizedBox(width: 6),
          SizedBox(width: 20, child: Text('$count', textAlign: TextAlign.end, style: Theme.of(context).textTheme.bodySmall)),
        ],
      ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats});

  final PlayerStats stats;

  @override
  Widget build(BuildContext context) {
    final hours = stats.hoursPlayed == stats.hoursPlayed.roundToDouble()
        ? stats.hoursPlayed.toInt().toString()
        : stats.hoursPlayed.toStringAsFixed(1);
    final items = [
      (icon: Icons.sports_score_outlined, value: '${stats.matchesPlayed}', label: 'Partidos jugados'),
      (icon: Icons.stadium_outlined, value: '${stats.courtsPlayed}', label: 'Canchas distintas'),
      (icon: Icons.timer_outlined, value: hours, label: 'Horas jugadas'),
      (icon: Icons.campaign_outlined, value: '${stats.matchesOrganized}', label: 'Partidos organizados'),
      (icon: Icons.event_available_outlined, value: '${stats.upcomingMatches}', label: 'Próximos partidos'),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 8.0;
        final columns = constraints.maxWidth >= 520 ? 3 : 2;
        final width = (constraints.maxWidth - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: Card(
                  margin: EdgeInsets.zero,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Icon(item.icon, color: Theme.of(context).colorScheme.primary),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.value,
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                              ),
                              Text(item.label, style: Theme.of(context).textTheme.bodySmall, maxLines: 2),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _TagChip extends StatelessWidget {
  const _TagChip({required this.label, required this.positive, required this.count});

  final String label;
  final bool positive;
  final int count;

  @override
  Widget build(BuildContext context) {
    final background = positive ? Colors.green.shade50 : Colors.red.shade50;
    final foreground = positive ? Colors.green.shade800 : Colors.red.shade800;
    return Chip(
      backgroundColor: background,
      side: BorderSide(color: foreground.withValues(alpha: 0.3)),
      avatar: Icon(positive ? Icons.thumb_up_alt_outlined : Icons.thumb_down_alt_outlined, size: 16, color: foreground),
      label: Text(count > 1 ? '$label ×$count' : label, style: TextStyle(color: foreground)),
    );
  }
}

class _SportsBars extends StatelessWidget {
  const _SportsBars({required this.sports});

  final List<({String name, int matches})> sports;

  @override
  Widget build(BuildContext context) {
    final max = sports.map((sport) => sport.matches).fold<int>(1, (a, b) => a > b ? a : b);
    return Column(
      children: [
        for (final sport in sports)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: [
                SizedBox(width: 96, child: Text(sport.name, overflow: TextOverflow.ellipsis)),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(value: sport.matches / max, minHeight: 10),
                  ),
                ),
                const SizedBox(width: 8),
                Text('${sport.matches} ${sport.matches == 1 ? 'partido' : 'partidos'}'),
              ],
            ),
          ),
      ],
    );
  }
}

/// Edit your own profile. Pops `true` when saved.
class EditProfileSheet extends StatefulWidget {
  const EditProfileSheet({required this.user, required this.userApiService, super.key});

  final UserModel user;
  final UserApiService userApiService;

  @override
  State<EditProfileSheet> createState() => _EditProfileSheetState();
}

class _EditProfileSheetState extends State<EditProfileSheet> {
  late final _nickname = TextEditingController(text: widget.user.nickname ?? '');
  late final _position = TextEditingController(text: widget.user.preferredPosition ?? '');
  late final _phone = TextEditingController(text: widget.user.phone ?? '');
  late String? _gender = widget.user.gender;
  late DateTime? _birthDate = widget.user.birthDate;
  late Set<int> _favoriteSportIds = {for (final sport in widget.user.favoriteSports) sport.id};
  late final _sportApiService = SportApiService(
    baseUrl: widget.userApiService.baseUrl,
    token: widget.userApiService.token,
  );
  bool _saving = false;

  @override
  void dispose() {
    _nickname.dispose();
    _position.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pickBirthDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthDate ?? DateTime(now.year - 25),
      firstDate: DateTime(1920),
      lastDate: DateTime(now.year - 5, now.month, now.day),
      initialDatePickerMode: DatePickerMode.year,
      helpText: 'Fecha de nacimiento',
    );
    if (picked != null) setState(() => _birthDate = picked);
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    String? clean(TextEditingController controller) =>
        controller.text.trim().isEmpty ? null : controller.text.trim();
    try {
      await widget.userApiService.updateMe(
        nickname: clean(_nickname),
        preferredPosition: clean(_position),
        phone: clean(_phone),
        birthDate: _birthDate,
        gender: _gender,
        favoriteSportIds: _favoriteSportIds.toList(),
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error is UserApiException ? error.message : 'No pudimos guardar tu perfil.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final birth = _birthDate;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Editar perfil', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            TextField(
              controller: _nickname,
              maxLength: 40,
              decoration: const InputDecoration(labelText: 'Apodo', hintText: 'Cómo te conocen en la cancha', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _position,
              maxLength: 60,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Posición preferida',
                hintText: 'Ej.: Arquero, Delantero, Base',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            InkWell(
              onTap: _pickBirthDate,
              borderRadius: BorderRadius.circular(4),
              child: InputDecorator(
                decoration: InputDecoration(
                  labelText: 'Fecha de nacimiento',
                  border: const OutlineInputBorder(),
                  suffixIcon: birth == null
                      ? const Icon(Icons.cake_outlined)
                      : IconButton(
                          tooltip: 'Quitar',
                          icon: const Icon(Icons.close),
                          onPressed: () => setState(() => _birthDate = null),
                        ),
                ),
                child: Text(birth == null ? 'Elegir fecha' : '${birth.day} ${_months[birth.month - 1]} ${birth.year}'),
              ),
            ),
            const SizedBox(height: 16),
            Text('Sexo', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'male', label: Text('Masculino')),
                ButtonSegment(value: 'female', label: Text('Femenino')),
              ],
              selected: {?_gender},
              emptySelectionAllowed: true,
              onSelectionChanged: (selection) => setState(() => _gender = selection.isEmpty ? _gender : selection.first),
            ),
            const SizedBox(height: 16),
            FavoriteSportsPicker(
              sportApiService: _sportApiService,
              selectedIds: _favoriteSportIds,
              enabled: !_saving,
              onChanged: (ids) => setState(() => _favoriteSportIds = ids),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Teléfono',
                helperText: 'No se muestra en tu perfil de jugador.',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Guardar'),
            ),
          ],
        ),
      ),
    );
  }
}
