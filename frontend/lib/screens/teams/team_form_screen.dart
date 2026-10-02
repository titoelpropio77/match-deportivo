import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/match_level_model.dart';
import '../../models/match_model.dart';
import '../../models/sport_model.dart';
import '../../models/team_model.dart';
import '../../models/user_model.dart';
import '../../services/match_level_api_service.dart';
import '../../services/sport_api_service.dart';
import '../../services/team_api_service.dart';
import '../../services/user_api_service.dart';
import 'widgets/team_badge.dart';

/// Create a team (pops the created [TeamModel]) or edit one when [team] is given.
class TeamFormScreen extends StatefulWidget {
  const TeamFormScreen({
    required this.teamApiService,
    required this.userApiService,
    required this.sportApiService,
    required this.matchLevelApiService,
    required this.currentUserId,
    this.team,
    super.key,
  });

  final TeamApiService teamApiService;
  final UserApiService userApiService;
  final SportApiService sportApiService;
  final MatchLevelApiService matchLevelApiService;
  final int currentUserId;
  final TeamModel? team;

  @override
  State<TeamFormScreen> createState() => _TeamFormScreenState();
}

class _TeamFormScreenState extends State<TeamFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(text: widget.team?.name ?? '');
  late final _shortNameController = TextEditingController(text: widget.team?.shortName ?? '');
  late final _descriptionController = TextEditingController(text: widget.team?.description ?? '');
  final _memberSearchController = TextEditingController();

  List<SportModel> _sports = const [];
  List<MatchLevelModel> _levels = const [];
  bool _loadingOptions = true;
  String? _optionsError;

  late int? _sportId = widget.team?.sport?.id;
  late int? _levelId = widget.team?.level?.id;
  late MatchGender _gender = widget.team?.gender ?? MatchGender.mixed;
  late String _color = widget.team?.primaryColor ?? teamColors.first;

  Uint8List? _logoBytes;
  String? _logoPath;
  String? _logoName;
  bool _removeLogo = false;

  final List<UserModel> _members = [];
  List<UserModel> _suggestions = const [];
  bool _searching = false;
  Timer? _debounce;

  bool _saving = false;

  bool get _editing => widget.team != null;

  @override
  void initState() {
    super.initState();
    _nameController.addListener(_refreshPreview);
    _shortNameController.addListener(_refreshPreview);
    _loadOptions();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _nameController.dispose();
    _shortNameController.dispose();
    _descriptionController.dispose();
    _memberSearchController.dispose();
    super.dispose();
  }

  void _refreshPreview() => setState(() {});

  Future<void> _loadOptions() async {
    setState(() {
      _loadingOptions = true;
      _optionsError = null;
    });
    try {
      final results = await Future.wait([widget.sportApiService.list(), widget.matchLevelApiService.list()]);
      if (!mounted) return;
      setState(() {
        _sports = results[0] as List<SportModel>;
        _levels = results[1] as List<MatchLevelModel>;
        _loadingOptions = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _optionsError = 'No pudimos cargar los deportes.';
        _loadingOptions = false;
      });
    }
  }

  Future<void> _pickLogo() async {
    try {
      final isMobile = !kIsWeb &&
          (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);
      if (isMobile) {
        final picked = await ImagePicker().pickImage(
          source: ImageSource.gallery,
          maxWidth: 800,
          maxHeight: 800,
          imageQuality: 90,
        );
        if (picked == null) return;
        final bytes = await picked.readAsBytes();
        if (!mounted) return;
        setState(() {
          _logoPath = picked.path;
          _logoBytes = bytes;
          _logoName = picked.name;
          _removeLogo = false;
        });
        return;
      }

      final file = await FilePicker.pickFile(type: FileType.image, dialogTitle: 'Elegir logo del equipo');
      if (file == null) return;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      setState(() {
        _logoPath = file.path;
        _logoBytes = bytes;
        _logoName = file.name;
        _removeLogo = false;
      });
    } catch (_) {
      _showMessage('No pudimos abrir la galería.');
    }
  }

  void _clearLogo() {
    setState(() {
      _logoBytes = null;
      _logoPath = null;
      _logoName = null;
      _removeLogo = widget.team?.logoUrl != null;
    });
  }

  void _onMemberSearchChanged(String query) {
    _debounce?.cancel();
    if (query.trim().isEmpty) {
      setState(() => _suggestions = const []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () async {
      setState(() => _searching = true);
      try {
        final results = await widget.userApiService.search(query);
        if (!mounted) return;
        final taken = {..._members.map((user) => user.id), widget.currentUserId};
        setState(() {
          _suggestions = results.where((user) => !taken.contains(user.id)).toList();
          _searching = false;
        });
      } catch (_) {
        if (!mounted) return;
        setState(() {
          _suggestions = const [];
          _searching = false;
        });
      }
    });
  }

  void _addMember(UserModel user) {
    setState(() {
      _members.add(user);
      _suggestions = const [];
      _memberSearchController.clear();
    });
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _saving = true);

    final logo = _logoBytes == null ? null : TeamLogoUpload(path: kIsWeb ? null : _logoPath, bytes: _logoBytes, filename: _logoName);
    final shortName = _shortNameController.text.trim().toUpperCase();
    final description = _descriptionController.text.trim();

    try {
      final team = _editing
          ? await widget.teamApiService.update(
              widget.team!.id,
              name: _nameController.text.trim(),
              sportId: _sportId!,
              shortName: shortName.isEmpty ? null : shortName,
              levelId: _levelId,
              gender: _gender.value,
              primaryColor: _color,
              description: description.isEmpty ? null : description,
              logo: logo,
              removeLogo: _removeLogo,
            )
          : await widget.teamApiService.create(
              name: _nameController.text.trim(),
              sportId: _sportId!,
              shortName: shortName.isEmpty ? null : shortName,
              levelId: _levelId,
              gender: _gender.value,
              primaryColor: _color,
              description: description.isEmpty ? null : description,
              memberIds: _members.map((user) => user.id).toList(),
              logo: logo,
            );
      if (!mounted) return;
      Navigator.of(context).pop(team);
    } catch (error) {
      _showMessage(error is TeamApiException ? error.message : 'No pudimos guardar el equipo.');
      if (mounted) setState(() => _saving = false);
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String get _previewInitials => TeamModel(
        id: 0,
        name: _nameController.text.trim().isEmpty ? 'Equipo' : _nameController.text.trim(),
        ownerId: 0,
        shortName: _shortNameController.text,
      ).initials;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Editar equipo' : 'Crear equipo')),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton(
            onPressed: _saving || _loadingOptions ? null : _save,
            child: _saving
                ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : Text(_editing ? 'Guardar cambios' : 'Crear equipo'),
          ),
        ),
      ),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loadingOptions) return const Center(child: CircularProgressIndicator());
    if (_optionsError != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_optionsError!),
            const SizedBox(height: 12),
            FilledButton(onPressed: _loadOptions, child: const Text('Reintentar')),
          ],
        ),
      );
    }

    final textTheme = Theme.of(context).textTheme;
    final hasLogo = _logoBytes != null || (!_removeLogo && widget.team?.logoUrl != null);

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: Column(
              children: [
                GestureDetector(
                  onTap: _pickLogo,
                  child: Stack(
                    children: [
                      TeamBadgeView(
                        initials: _previewInitials,
                        color: _color,
                        logoBytes: _logoBytes,
                        logoUrl: _removeLogo ? null : widget.team?.logoUrl,
                        size: 104,
                      ),
                      Positioned(
                        right: 0,
                        bottom: 0,
                        child: CircleAvatar(
                          radius: 16,
                          backgroundColor: Theme.of(context).colorScheme.primary,
                          child: Icon(Icons.photo_camera_outlined, size: 18, color: Theme.of(context).colorScheme.onPrimary),
                        ),
                      ),
                    ],
                  ),
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextButton(onPressed: _pickLogo, child: Text(hasLogo ? 'Cambiar logo' : 'Subir logo (opcional)')),
                    if (hasLogo) TextButton(onPressed: _clearLogo, child: const Text('Quitar')),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _nameController,
            textCapitalization: TextCapitalization.words,
            maxLength: 60,
            decoration: const InputDecoration(labelText: 'Nombre del equipo *', border: OutlineInputBorder()),
            validator: (value) => (value?.trim().length ?? 0) < 2 ? 'Escribe el nombre del equipo.' : null,
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _shortNameController,
            textCapitalization: TextCapitalization.characters,
            maxLength: 4,
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9]'))],
            decoration: const InputDecoration(
              labelText: 'Abreviatura',
              helperText: 'Para marcadores y listas, ej. TIG',
              border: OutlineInputBorder(),
            ),
            validator: (value) {
              final length = value?.trim().length ?? 0;
              return length == 1 ? 'Usa de 2 a 4 caracteres.' : null;
            },
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            initialValue: _sportId,
            decoration: const InputDecoration(labelText: 'Deporte *', border: OutlineInputBorder()),
            items: [for (final sport in _sports) DropdownMenuItem(value: sport.id, child: Text(sport.name))],
            onChanged: (value) => setState(() => _sportId = value),
            validator: (value) => value == null ? 'Elige el deporte del equipo.' : null,
          ),
          const SizedBox(height: 16),
          Text('Categoría', style: textTheme.titleSmall),
          const SizedBox(height: 8),
          SegmentedButton<MatchGender>(
            segments: [
              for (final gender in MatchGender.values) ButtonSegment(value: gender, label: Text(gender.label)),
            ],
            selected: {_gender},
            onSelectionChanged: (selection) => setState(() => _gender = selection.first),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int?>(
            initialValue: _levelId,
            decoration: const InputDecoration(labelText: 'Nivel', border: OutlineInputBorder()),
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('Sin especificar')),
              for (final level in _levels) DropdownMenuItem<int?>(value: level.id, child: Text(level.name)),
            ],
            onChanged: (value) => setState(() => _levelId = value),
          ),
          const SizedBox(height: 16),
          Text('Color del equipo', style: textTheme.titleSmall),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final color in teamColors)
                _ColorSwatch(color: color, selected: color == _color, onTap: () => setState(() => _color = color)),
            ],
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _descriptionController,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Descripción',
              hintText: 'Ej.: Jugamos los jueves en la noche, buscamos arquero.',
              border: OutlineInputBorder(),
              alignLabelWithHint: true,
            ),
          ),
          if (!_editing) ...[
            const SizedBox(height: 8),
            Text('Jugadores', style: textTheme.titleMedium),
            Text(
              'Tú serás el capitán. Puedes agregar jugadores ahora o después.',
              style: textTheme.bodySmall,
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _memberSearchController,
              autocorrect: false,
              enableSuggestions: false,
              decoration: InputDecoration(
                labelText: 'Buscar por nombre o email',
                prefixIcon: const Icon(Icons.person_search_outlined),
                border: const OutlineInputBorder(),
                suffixIcon: _searching
                    ? const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                      )
                    : null,
              ),
              onChanged: _onMemberSearchChanged,
            ),
            if (_suggestions.isNotEmpty)
              Card(
                margin: const EdgeInsets.only(top: 4),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final user in _suggestions)
                      ListTile(
                        leading: const Icon(Icons.person_add_alt_1_outlined),
                        title: Text(user.name),
                        subtitle: Text(user.email),
                        onTap: () => _addMember(user),
                      ),
                  ],
                ),
              ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final user in _members)
                  InputChip(
                    avatar: CircleAvatar(child: Text(user.name.characters.first.toUpperCase())),
                    label: Text(user.name),
                    onDeleted: () => setState(() => _members.removeWhere((item) => item.id == user.id)),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _ColorSwatch extends StatelessWidget {
  const _ColorSwatch({required this.color, required this.selected, required this.onTap});

  final String color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final value = parseTeamColor(color);
    return Semantics(
      button: true,
      selected: selected,
      label: 'Color $color',
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: value,
            shape: BoxShape.circle,
            border: Border.all(
              color: selected ? Theme.of(context).colorScheme.onSurface : Colors.transparent,
              width: 3,
            ),
          ),
          child: selected
              ? Icon(Icons.check, size: 18, color: value.computeLuminance() > 0.5 ? Colors.black : Colors.white)
              : null,
        ),
      ),
    );
  }
}
