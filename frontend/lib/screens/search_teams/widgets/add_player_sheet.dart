import 'dart:async';

import 'package:flutter/material.dart';

import '../../../models/user_model.dart';
import '../../../services/user_api_service.dart';

/// Search sheet for the organizer to find a player by nickname, name or email.
class AddPlayerSheet extends StatefulWidget {
  const AddPlayerSheet({
    required this.userApiService,
    required this.excludedUserIds,
    required this.onSelect,
    this.title = 'Agregar jugador',
    super.key,
  });

  final UserApiService userApiService;
  final Set<int> excludedUserIds;
  final ValueChanged<UserModel> onSelect;
  final String title;

  @override
  State<AddPlayerSheet> createState() => _AddPlayerSheetState();
}

class _AddPlayerSheetState extends State<AddPlayerSheet> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  List<UserModel> _suggestions = const [];
  bool _searching = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onQueryChanged(String query) {
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
        setState(() {
          _suggestions = results
              .where((user) => !widget.excludedUserIds.contains(user.id))
              .toList();
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

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            widget.title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _searchController,
            autofocus: true,
            decoration: InputDecoration(
              labelText: 'Buscar por nickname, nombre o correo',
              border: const OutlineInputBorder(),
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searching
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : null,
            ),
            onChanged: _onQueryChanged,
          ),
          const SizedBox(height: 8),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 280),
            child: _suggestions.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text(
                      _searchController.text.trim().isEmpty
                          ? 'Escribe para buscar un jugador.'
                          : _searching
                              ? 'Buscando...'
                              : 'No encontramos jugadores.',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                  )
                : ListView(
                    shrinkWrap: true,
                    children: _suggestions
                        .map(
                          (user) => ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(user.nickname ?? user.name),
                            subtitle: Text(
                              user.nickname != null
                                  ? '${user.name} · ${user.email}'
                                  : user.email,
                            ),
                            onTap: () => widget.onSelect(user),
                          ),
                        )
                        .toList(),
                  ),
          ),
        ],
      ),
    );
  }
}
