import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/banner_model.dart';

class BannerApiException implements Exception {
  const BannerApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'BannerApiException($statusCode): $message';
}

/// Home carousel banners (public endpoint, managed from the admin panel).
class BannerApiService {
  BannerApiService({required String baseUrl, http.Client? client})
    : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
      _client = client ?? http.Client();

  final String _baseUrl;
  final http.Client _client;

  Future<List<BannerModel>> list() async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/banners'),
      headers: const {'Accept': 'application/json'},
    );
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw BannerApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    final data = decoded['data'];
    if (data is! List) {
      throw const FormatException('The banners response has an invalid format');
    }

    return data
        .map((item) => BannerModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }
}
