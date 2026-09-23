import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/sport_model.dart';

class SportApiException implements Exception {
  const SportApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'SportApiException($statusCode): $message';
}

class SportApiService {
  SportApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;
  final String? _token;
  final http.Client _client;

  Future<List<SportModel>> list() async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/sports'),
      headers: _headers(),
    );
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw SportApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    final data = decoded['data'];
    if (data is! List) {
      throw const FormatException('The sports response has an invalid format');
    }

    return data
        .map((item) => SportModel.fromJson(item as Map<String, dynamic>))
        .toList();
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
