import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/ranking_model.dart';

class RankingApiException implements Exception {
  const RankingApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'RankingApiException($statusCode): $message';
}

/// Public leaderboards (centers, teams, players, products and stores).
class RankingApiService {
  RankingApiService({required String baseUrl, http.Client? client})
    : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
      _client = client ?? http.Client();

  final String _baseUrl;
  final http.Client _client;

  Future<RankingModel> fetch({
    RankingPeriod period = RankingPeriod.all,
    int? cityId,
  }) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/rankings').replace(
        queryParameters: {
          'period': period.value,
          if (cityId != null) 'city_id': cityId.toString(),
        },
      ),
      headers: const {'Accept': 'application/json'},
    );
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RankingApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    final data = decoded['data'];
    if (data is! Map<String, dynamic>) {
      throw const FormatException(
        'The rankings response has an invalid format',
      );
    }
    return RankingModel.fromJson(data);
  }
}
