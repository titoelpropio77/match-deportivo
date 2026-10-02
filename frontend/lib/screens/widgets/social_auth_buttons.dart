import 'package:flutter/material.dart';

import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../../services/social_sign_in_service.dart';

/// "o continúa con" + Google / Facebook buttons, shared by login and register.
///
/// Signs in with the provider, exchanges its token for a session in the backend and
/// calls [onAuthenticated]; a new account then goes through "Completa tu perfil".
class SocialAuthButtons extends StatefulWidget {
  const SocialAuthButtons({
    required this.authService,
    required this.tokenStorage,
    required this.onAuthenticated,
    this.socialSignIn,
    this.enabled = true,
    this.onBusyChanged,
    super.key,
  });

  final AuthService authService;
  final TokenStorage tokenStorage;
  final void Function(UserModel user, String token) onAuthenticated;

  /// Defaults to the native Google / Facebook SDKs.
  final SocialSignInService? socialSignIn;
  final bool enabled;

  /// Lets the screen disable its own form while a provider is signing in.
  final ValueChanged<bool>? onBusyChanged;

  @override
  State<SocialAuthButtons> createState() => _SocialAuthButtonsState();
}

class _SocialAuthButtonsState extends State<SocialAuthButtons> {
  late final SocialSignInService _socialSignIn =
      widget.socialSignIn ?? SocialSignInService();

  SocialProvider? _busyWith;
  String? _errorMessage;

  Future<void> _signIn(SocialProvider provider) async {
    setState(() {
      _busyWith = provider;
      _errorMessage = null;
    });
    widget.onBusyChanged?.call(true);

    try {
      final accessToken = await _socialSignIn.accessToken(provider);
      if (accessToken == null) return;

      final result = await widget.authService.socialLogin(
        provider: provider,
        accessToken: accessToken,
      );
      await widget.tokenStorage.save(result.token);
      if (!mounted) return;

      widget.onAuthenticated(result.user, result.token);
      // Closes the register screen when it was pushed over the login.
      Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (error) {
      await _socialSignIn.signOut();
      if (!mounted) return;
      setState(() => _errorMessage = _messageFor(error, provider));
    } finally {
      if (mounted) setState(() => _busyWith = null);
      widget.onBusyChanged?.call(false);
    }
  }

  String _messageFor(Object error, SocialProvider provider) {
    if (error is SocialSignInException) return error.message;
    if (error is AuthApiException) {
      return error.fieldErrors?.values.firstOrNull?.firstOrNull ?? error.message;
    }
    return 'No pudimos ingresar con ${provider.label}. Intenta de nuevo.';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final enabled = widget.enabled && _busyWith == null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Expanded(child: Divider()),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(
                'o continúa con',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
            const Expanded(child: Divider()),
          ],
        ),
        const SizedBox(height: 16),
        _ProviderButton(
          key: const ValueKey('social-google'),
          label: 'Continuar con Google',
          icon: const _GoogleMark(),
          loading: _busyWith == SocialProvider.google,
          onPressed: enabled ? () => _signIn(SocialProvider.google) : null,
        ),
        const SizedBox(height: 12),
        _ProviderButton(
          key: const ValueKey('social-facebook'),
          label: 'Continuar con Facebook',
          icon: const Icon(Icons.facebook, color: Color(0xFF1877F2), size: 22),
          loading: _busyWith == SocialProvider.facebook,
          onPressed: enabled ? () => _signIn(SocialProvider.facebook) : null,
        ),
        if (_errorMessage != null) ...[
          const SizedBox(height: 12),
          Text(
            _errorMessage!,
            style: TextStyle(color: theme.colorScheme.error),
            textAlign: TextAlign.center,
          ),
        ],
      ],
    );
  }
}

class _ProviderButton extends StatelessWidget {
  const _ProviderButton({
    required this.label,
    required this.icon,
    required this.loading,
    required this.onPressed,
    super.key,
  });

  final String label;
  final Widget icon;
  final bool loading;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 22,
            height: 22,
            child: loading
                ? const Padding(
                    padding: EdgeInsets.all(2),
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : icon,
          ),
          const SizedBox(width: 12),
          Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
        ],
      ),
    );
  }
}

/// Google's "G" (no image asset needed).
class _GoogleMark extends StatelessWidget {
  const _GoogleMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
      child: const Text(
        'G',
        style: TextStyle(
          color: Color(0xFF4285F4),
          fontWeight: FontWeight.w800,
          fontSize: 17,
          height: 1,
        ),
      ),
    );
  }
}
