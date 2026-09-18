import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_model.dart';

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
  final http.Client _client;

  Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    final response = await _client.post(
      _uri('/api/register'),
      headers: _headers(),
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
      }),
    );

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
      final rawErrors = decoded['errors'];
      final fieldErrors = rawErrors is Map<String, dynamic>
          ? rawErrors.map(
              (key, value) => MapEntry(key, List<String>.from(value as List)),
            )
          : null;

      throw AuthApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
        fieldErrors: fieldErrors,
      );
    }

    return AuthResult(
      user: UserModel.fromJson(decoded['user'] as Map<String, dynamic>),
      token: decoded['token'] as String,
    );
  }
}
