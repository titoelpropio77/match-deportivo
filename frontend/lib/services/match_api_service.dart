import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/match_model.dart';

class MatchApiException implements Exception {
  const MatchApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'MatchApiException($statusCode): $message';
}

class MatchApiService {
  MatchApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;
  final String? _token;
  final http.Client _client;

  String get baseUrl => _baseUrl;
  String? get token => _token;

  Future<List<MatchModel>> listOpenMatches({
    MatchSport? sport,
    String? location,
    int page = 1,
  }) async {
    final queryParameters = <String, String>{
      if (sport != null) 'sport': sport.value,
      if (location != null && location.trim().isNotEmpty)
        'location': location.trim(),
      'page': page.toString(),
    };
    final response = await _client.get(
      _uri('/api/matches', queryParameters),
      headers: _headers(),
    );
    final body = _decode(response);
    final data = body['data'];

    if (data is! List) {
      throw const FormatException('The matches response has an invalid format');
    }

    return data
        .map((item) => MatchModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<MatchModel> createMatch({
    required MatchSport sport,
    required MatchLevel level,
    required String location,
    required DateTime startTime,
    required DateTime endTime,
    required int maxPlayers,
    List<int> playerIds = const [],
  }) async {
    final response = await _client.post(
      _uri('/api/matches'),
      headers: _headers(),
      body: jsonEncode({
        'sport': sport.value,
        'level': level.value,
        'location': location,
        'start_time': startTime.toIso8601String(),
        'end_time': endTime.toIso8601String(),
        'max_players': maxPlayers,
        'player_ids': playerIds,
      }),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<MatchModel> joinMatch(
    int matchId, {
    int quantitySlots = 1,
  }) async {
    final response = await _client.post(
      _uri('/api/matches/$matchId/join'),
      headers: _headers(),
      body: jsonEncode({'quantity_slots': quantitySlots}),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  void dispose() => _client.close();

  Uri _uri(String path, [Map<String, String>? queryParameters]) {
    return Uri.parse('$_baseUrl$path').replace(
      queryParameters: queryParameters,
    );
  }

  Map<String, String> _headers() {
    final token = _token;

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw MatchApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    return decoded;
  }
}