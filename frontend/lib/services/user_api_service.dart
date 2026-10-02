import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/player_profile_model.dart';
import '../models/user_model.dart';

class UserApiException implements Exception {
  const UserApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'UserApiException($statusCode): $message';
}

class UserApiService {
  UserApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;

  String get baseUrl => _baseUrl;
  String? get token => _token;
  final String? _token;
  final http.Client _client;

  Future<List<UserModel>> search(String query) async {
    final trimmed = query.trim();
    if (trimmed.isEmpty) return const [];

    final response = await _client.get(
      Uri.parse('$_baseUrl/api/users/search').replace(
        queryParameters: {'query': trimmed},
      ),
      headers: _headers(),
    );
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw UserApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    final data = decoded['data'];
    if (data is! List) {
      throw const FormatException('The users response has an invalid format');
    }

    return data
        .map((item) => UserModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Public profile of a player with stats and rating.
  Future<PlayerProfileModel> profile(int userId) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/users/$userId/profile'),
      headers: _headers(),
    );
    return PlayerProfileModel.fromJson(_decode(response)['data'] as Map<String, dynamic>);
  }

  /// Edits the signed-in user's profile; null values clear optional fields.
  Future<UserModel> updateMe({
    String? nickname,
    String? phone,
    String? preferredPosition,
    DateTime? birthDate,
    String? gender,
    List<int>? favoriteSportIds,
  }) async {
    String? date(DateTime? value) => value == null
        ? null
        : '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
    final response = await _client.patch(
      Uri.parse('$_baseUrl/api/me'),
      headers: _headers(),
      body: jsonEncode({
        'nickname': nickname,
        'phone': phone,
        'preferred_position': preferredPosition,
        'birth_date': date(birthDate),
        'gender': ?gender,
        'favorite_sport_ids': ?favoriteSportIds,
      }),
    );
    return UserModel.fromJson(_decode(response)['data'] as Map<String, dynamic>);
  }

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = decoded['errors'];
      final firstError = errors is Map && errors.values.isNotEmpty ? (errors.values.first as List).first as String : null;
      throw UserApiException(
        firstError ?? decoded['message'] as String? ?? 'No pudimos completar la operación.',
        response.statusCode,
      );
    }
    return decoded;
  }

  void dispose() => _client.close();

  Map<String, String> _headers() {
    final token = _token;

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }
}
