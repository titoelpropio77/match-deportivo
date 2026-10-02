import 'package:flutter/foundation.dart';
import 'package:flutter_facebook_auth/flutter_facebook_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config/app_config.dart';

/// Providers the backend accepts in `POST /api/auth/{provider}` (Laravel Socialite).
enum SocialProvider {
  google('google', 'Google'),
  facebook('facebook', 'Facebook');

  const SocialProvider(this.key, this.label);

  final String key;
  final String label;
}

class SocialSignInException implements Exception {
  const SocialSignInException(this.message);

  final String message;

  @override
  String toString() => 'SocialSignInException: $message';
}

/// Signs in with the provider's native SDK and returns its access token, which the
/// backend validates with Socialite. Returns null when the person cancels.
class SocialSignInService {
  SocialSignInService();

  static const _googleScopes = ['email', 'profile'];
  static bool _googleInitialized = false;

  /// Google and Facebook login are only set up for the Android / iOS app.
  bool get isSupported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  Future<String?> accessToken(SocialProvider provider) async {
    if (!isSupported) {
      throw const SocialSignInException(
        'El ingreso con Google o Facebook está disponible en la app para Android e iOS.',
      );
    }

    return switch (provider) {
      SocialProvider.google => _googleAccessToken(),
      SocialProvider.facebook => _facebookAccessToken(),
    };
  }

  /// Signs out of the provider so the next attempt lets the person choose another account.
  Future<void> signOut() async {
    if (!isSupported) return;
    try {
      if (_googleInitialized) await GoogleSignIn.instance.signOut();
      await FacebookAuth.instance.logOut();
    } catch (_) {
      // Nothing to clean up if the provider was never used.
    }
  }

  Future<String?> _googleAccessToken() async {
    try {
      final google = GoogleSignIn.instance;
      if (!_googleInitialized) {
        await google.initialize(serverClientId: AppConfig.googleServerClientId);
        _googleInitialized = true;
      }

      final account = await google.authenticate(scopeHint: _googleScopes);
      final authorization =
          await account.authorizationClient.authorizationForScopes(_googleScopes) ??
              await account.authorizationClient.authorizeScopes(_googleScopes);
      return authorization.accessToken;
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) return null;
      throw const SocialSignInException('No pudimos ingresar con Google. Intenta de nuevo.');
    }
  }

  Future<String?> _facebookAccessToken() async {
    // `enabled` tracking returns a classic token, the only kind the Graph API (and Socialite) accepts.
    final result = await FacebookAuth.instance.login(
      permissions: const ['email', 'public_profile'],
      loginTracking: LoginTracking.enabled,
    );

    switch (result.status) {
      case LoginStatus.success:
        final token = result.accessToken?.tokenString;
        if (token == null || token.isEmpty) {
          throw const SocialSignInException('No pudimos ingresar con Facebook. Intenta de nuevo.');
        }
        return token;
      case LoginStatus.cancelled:
        return null;
      case LoginStatus.failed:
      case LoginStatus.operationInProgress:
        throw const SocialSignInException('No pudimos ingresar con Facebook. Intenta de nuevo.');
    }
  }
}
