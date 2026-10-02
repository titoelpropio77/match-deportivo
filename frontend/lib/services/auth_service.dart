import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_model.dart';
import 'social_sign_in_service.dart';

class AuthApiException implements Exception {
  const AuthApiException(this.message, this.statusCode, {this.fieldErrors});

  final String message;
  final int statusCode;
  final Map<String, List<String>>? fieldErrors;

  @override
  String toString() => 'AuthApiException($statusCode): $message';
}

class AuthResult {
  const AuthResult({required this.user, required this.token});

  final UserModel user;
  final String token;
}

/// Persists the Sanctum bearer token on the device.
class TokenStorage {
  static const _tokenKey = 'auth_token';

  Future<void> save(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  Future<String?> read() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
  }
}

class AuthService {
  AuthService({
    required String baseUrl,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;

  String get baseUrl => _baseUrl;
  final http.Client _client;

  Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    required String gender,
    DateTime? birthDate,
    List<int> favoriteSportIds = const [],
    String? photoPath,
    List<int>? photoBytes,
    String? photoFilename,
  }) async {
    final request = http.MultipartRequest('POST', _uri('/api/register'));
    request.headers['Accept'] = 'application/json';
    request.fields.addAll({
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
      'gender': gender,
      if (birthDate != null)
        'birth_date':
            '${birthDate.year.toString().padLeft(4, '0')}-${birthDate.month.toString().padLeft(2, '0')}-${birthDate.day.toString().padLeft(2, '0')}',
      for (var index = 0; index < favoriteSportIds.length; index++)
        'favorite_sport_ids[$index]': '${favoriteSportIds[index]}',
    });

    if (photoPath != null && photoPath.isNotEmpty) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'photo',
          photoPath,
          filename: photoFilename,
        ),
      );
    } else if (photoBytes != null && photoBytes.isNotEmpty) {
      request.files.add(
        http.MultipartFile.fromBytes(
          'photo',
          photoBytes,
          filename: photoFilename ?? 'avatar.jpg',
        ),
      );
    }

    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);

    return _decodeAuthResult(response);
  }

  Future<AuthResult> login({
    required String email,
    required String password,
  }) async {
    final response = await _client.post(
      _uri('/api/login'),
      headers: _headers(),
      body: jsonEncode({'email': email, 'password': password}),
    );

    return _decodeAuthResult(response);
  }

  /// Signs up or logs in with the access token of Google / Facebook (`POST /api/auth/{provider}`).
  Future<AuthResult> socialLogin({
    required SocialProvider provider,
    required String accessToken,
  }) async {
    final response = await _client.post(
      _uri('/api/auth/${provider.key}'),
      headers: _headers(),
      body: jsonEncode({'access_token': accessToken}),
    );

    return _decodeAuthResult(response);
  }

  /// "Completa tu perfil" after signing up with Google / Facebook.
  Future<UserModel> completeProfile({
    required String token,
    required String name,
    required String gender,
    String? nickname,
    String? phone,
    String? preferredPosition,
    DateTime? birthDate,
    List<int> favoriteSportIds = const [],
  }) async {
    String? clean(String? value) =>
        value == null || value.trim().isEmpty ? null : value.trim();

    final response = await _client.post(
      _uri('/api/me/complete-profile'),
      headers: _headers(token: token),
      body: jsonEncode({
        'name': name.trim(),
        'gender': gender,
        'nickname': clean(nickname),
        'phone': clean(phone),
        'preferred_position': clean(preferredPosition),
        'birth_date': birthDate == null
            ? null
            : '${birthDate.year.toString().padLeft(4, '0')}-${birthDate.month.toString().padLeft(2, '0')}-${birthDate.day.toString().padLeft(2, '0')}',
        'favorite_sport_ids': favoriteSportIds,
      }),
    );

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AuthApiException(
        decoded['message'] as String? ?? 'No pudimos guardar tus datos.',
        response.statusCode,
        fieldErrors: _fieldErrors(decoded),
      );
    }

    return UserModel.fromJson(decoded['user'] as Map<String, dynamic>);
  }

  Future<void> logout(String token) async {
    await _client.post(
      _uri('/api/logout'),
      headers: _headers(token: token),
    );
  }

  /// Validates that [token] still corresponds to an active session.
  Future<UserModel> me(String token) async {
    final response = await _client.get(
      _uri('/api/me'),
      headers: _headers(token: token),
    );

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AuthApiException(
        decoded['message'] as String? ?? 'Session expired',
        response.statusCode,
      );
    }

    return UserModel.fromJson(decoded['user'] as Map<String, dynamic>);
  }

  void dispose() => _client.close();

  Uri _uri(String path) => Uri.parse('$_baseUrl$path');

  Map<String, String> _headers({String? token}) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  AuthResult _decodeAuthResult(http.Response response) {
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AuthApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
        fieldErrors: _fieldErrors(decoded),
      );
    }

    return AuthResult(
      user: UserModel.fromJson(decoded['user'] as Map<String, dynamic>),
      token: decoded['token'] as String,
    );
  }

  Map<String, List<String>>? _fieldErrors(Map<String, dynamic> decoded) {
    final rawErrors = decoded['errors'];
    return rawErrors is Map<String, dynamic>
        ? rawErrors.map(
            (key, value) => MapEntry(key, List<String>.from(value as List)),
          )
        : null;
  }
}
