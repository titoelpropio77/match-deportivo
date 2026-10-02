import 'package:flutter/material.dart';

import '../models/user_model.dart';
import '../services/auth_service.dart';
import '../services/sport_api_service.dart';
import 'profile/favorite_sports_picker.dart';

/// "Completa tu perfil": after signing up with Google / Facebook, asks for the data the
/// provider does not give before entering the app. Shown by `_AuthGate` while
/// `user.profileCompleted` is false, so it also comes back if the app was closed halfway.
class CompleteProfileScreen extends StatefulWidget {
  const CompleteProfileScreen({
    required this.user,
    required this.token,
    required this.authService,
    required this.onCompleted,
    required this.onLogout,
    this.sportApiService,
    super.key,
  });

  final UserModel user;
  final String token;
  final AuthService authService;
  final ValueChanged<UserModel> onCompleted;
  final VoidCallback onLogout;

  /// Lists the sports for "Mis deportes favoritos"; defaults to one on [authService]'s URL.
  final SportApiService? sportApiService;

  @override
  State<CompleteProfileScreen> createState() => _CompleteProfileScreenState();
}

class _CompleteProfileScreenState extends State<CompleteProfileScreen> {
  late final SportApiService _sportApiService = widget.sportApiService ??
      SportApiService(baseUrl: widget.authService.baseUrl);
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(text: widget.user.name);
  late final _nicknameController =
      TextEditingController(text: widget.user.nickname ?? '');
  late final _phoneController = TextEditingController(text: widget.user.phone ?? '');
  late final _positionController =
      TextEditingController(text: widget.user.preferredPosition ?? '');

  late String? _gender = widget.user.gender;
  late DateTime? _birthDate = widget.user.birthDate;
  late Set<int> _favoriteSportIds = {
    for (final sport in widget.user.favoriteSports) sport.id,
  };

  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _nameController.dispose();
    _nicknameController.dispose();
    _phoneController.dispose();
    _positionController.dispose();
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

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final user = await widget.authService.completeProfile(
        token: widget.token,
        name: _nameController.text,
        gender: _gender!,
        nickname: _nicknameController.text,
        phone: _phoneController.text,
        preferredPosition: _positionController.text,
        birthDate: _birthDate,
        favoriteSportIds: _favoriteSportIds.toList(),
      );
      if (!mounted) return;
      widget.onCompleted(user);
    } catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = _messageFor(error));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _messageFor(Object error) {
    if (error is AuthApiException) {
      return error.fieldErrors?.values.firstOrNull?.firstOrNull ?? error.message;
    }
    return 'No pudimos conectar con el servidor.';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final photoUrl = widget.user.photoUrl;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Completa tu perfil'),
        automaticallyImplyLeading: false,
        actions: [
          TextButton(
            onPressed: _isLoading ? null : widget.onLogout,
            child: const Text('Salir'),
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: CircleAvatar(
                        radius: 44,
                        backgroundColor: theme.colorScheme.surfaceContainerHighest,
                        backgroundImage:
                            photoUrl == null ? null : NetworkImage(photoUrl),
                        child: photoUrl == null
                            ? Icon(
                                Icons.person_outline,
                                size: 40,
                                color: theme.colorScheme.onSurfaceVariant,
                              )
                            : null,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      '¡Bienvenido, ${widget.user.name.split(' ').first}!',
                      style: theme.textTheme.titleLarge,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Completa unos datos para armar tu perfil de jugador.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    if (_errorMessage != null) ...[
                      Text(
                        _errorMessage!,
                        style: TextStyle(color: theme.colorScheme.error),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                    ],
                    TextFormField(
                      controller: _nameController,
                      enabled: !_isLoading,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Nombre',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (value) => value == null || value.trim().isEmpty
                          ? 'El nombre es obligatorio.'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _nicknameController,
                      enabled: !_isLoading,
                      maxLength: 40,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Apodo (opcional)',
                        helperText: 'Así te verán los demás jugadores.',
                        prefixIcon: Icon(Icons.badge_outlined),
                        counterText: '',
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phoneController,
                      enabled: !_isLoading,
                      keyboardType: TextInputType.phone,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Teléfono (opcional)',
                        helperText: 'Para coordinar partidos por WhatsApp.',
                        prefixIcon: Icon(Icons.phone_outlined),
                      ),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: _gender,
                      decoration: const InputDecoration(
                        labelText: 'Género',
                        prefixIcon: Icon(Icons.wc_outlined),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'male', child: Text('Hombre')),
                        DropdownMenuItem(value: 'female', child: Text('Mujer')),
                      ],
                      onChanged: _isLoading
                          ? null
                          : (value) => setState(() => _gender = value),
                      validator: (value) => value == null || value.isEmpty
                          ? 'El género es obligatorio.'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    InkWell(
                      onTap: _isLoading ? null : _pickBirthDate,
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Fecha de nacimiento (opcional)',
                          helperText: 'Para mostrar tu edad en tu perfil de jugador.',
                          prefixIcon: Icon(Icons.cake_outlined),
                        ),
                        child: Text(
                          _birthDate == null
                              ? 'Elegir fecha'
                              : '${_birthDate!.day.toString().padLeft(2, '0')}/${_birthDate!.month.toString().padLeft(2, '0')}/${_birthDate!.year}',
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _positionController,
                      enabled: !_isLoading,
                      maxLength: 60,
                      textInputAction: TextInputAction.done,
                      decoration: const InputDecoration(
                        labelText: 'Posición preferida (opcional)',
                        hintText: 'Ej. Delantero, arquero, drive',
                        prefixIcon: Icon(Icons.sports_soccer_outlined),
                        counterText: '',
                      ),
                    ),
                    const SizedBox(height: 20),
                    FavoriteSportsPicker(
                      sportApiService: _sportApiService,
                      selectedIds: _favoriteSportIds,
                      enabled: !_isLoading,
                      onChanged: (ids) => setState(() => _favoriteSportIds = ids),
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _isLoading ? null : _submit,
                      child: _isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Continuar'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
