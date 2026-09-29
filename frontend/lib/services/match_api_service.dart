import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/match_model.dart';
import '../models/rating_tag_model.dart';

class PaginatedMatches {
  const PaginatedMatches({
    required this.matches,
    required this.currentPage,
    required this.lastPage,
  });

  final List<MatchModel> matches;
  final int currentPage;
  final int lastPage;
}

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
    int? sportId,
    int? courtId,
    DateTime? date,
    int page = 1,
  }) async {
    final queryParameters = <String, String>{
      if (sportId != null) 'sport_id': sportId.toString(),
      if (courtId != null) 'court_id': courtId.toString(),
      if (date != null)
        'date':
            '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
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

  /// Lists matches created by the authenticated user that have not ended yet.
  Future<List<MatchModel>> listOrganizedMatches({int page = 1}) async {
    final response = await _client.get(
      _uri('/api/matches/organized', {'page': page.toString()}),
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

  /// Lists past matches created by the authenticated user, five per page.
  Future<PaginatedMatches> listPastOrganizedMatches({int page = 1}) async {
    final response = await _client.get(
      _uri('/api/matches/organized/past', {'page': page.toString()}),
      headers: _headers(),
    );
    final body = _decode(response);
    final data = body['data'];

    if (data is! List) {
      throw const FormatException('The matches response has an invalid format');
    }

    return PaginatedMatches(
      matches: data
          .map((item) => MatchModel.fromJson(item as Map<String, dynamic>))
          .toList(),
      currentPage: (body['current_page'] as num?)?.toInt() ?? page,
      lastPage: (body['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  /// Lists the authenticated user's upcoming matches (joined as a player).
  Future<List<MatchModel>> listMyMatches({int page = 1}) async {
    final response = await _client.get(
      _uri('/api/matches/mine', {'page': page.toString()}),
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
    required int sportId,
    required int levelId,
    required int courtId,
    required String gender,
    required DateTime startTime,
    required DateTime endTime,
    required int maxPlayers,
    List<int> playerIds = const [],
    bool joinAsPlayer = false,
    String? paymentQrPath,
    List<int>? paymentQrBytes,
    String? paymentQrFilename,
  }) async {
    final request = http.MultipartRequest('POST', _uri('/api/matches'));
    request.headers.addAll(_headers(json: false));
    request.fields.addAll({
      'sport_id': sportId.toString(),
      'level_id': levelId.toString(),
      'court_id': courtId.toString(),
      'gender': gender,
      'start_time': startTime.toIso8601String(),
      'end_time': endTime.toIso8601String(),
      'max_players': maxPlayers.toString(),
      'join_as_player': joinAsPlayer ? '1' : '0',
    });
    for (var index = 0; index < playerIds.length; index++) {
      request.fields['player_ids[$index]'] = playerIds[index].toString();
    }

    if (paymentQrPath != null && paymentQrPath.isNotEmpty) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'payment_qr',
          paymentQrPath,
          filename: paymentQrFilename,
        ),
      );
    } else if (paymentQrBytes != null && paymentQrBytes.isNotEmpty) {
      request.files.add(
        http.MultipartFile.fromBytes(
          'payment_qr',
          paymentQrBytes,
          filename: paymentQrFilename ?? 'payment-qr.jpg',
        ),
      );
    }

    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);
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

  /// Removes the authenticated user's registration from a match.
  Future<MatchModel> leaveMatch(int matchId) async {
    final response = await _client.delete(
      _uri('/api/matches/$matchId/leave'),
      headers: _headers(),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Adds a player to a match. Organizer only.
  Future<MatchModel> addPlayer({
    required int matchId,
    required int userId,
  }) async {
    final response = await _client.post(
      _uri('/api/matches/$matchId/players'),
      headers: _headers(),
      body: jsonEncode({'user_id': userId}),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Accepts or rejects a pending join request.
  Future<MatchModel> reviewPlayer({
    required int matchId,
    required int playerId,
    required String action,
  }) async {
    final response = await _client.post(
      _uri('/api/matches/$matchId/players/$playerId/review'),
      headers: _headers(),
      body: jsonEncode({'action': action}),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Removes a player from a match. Organizer only.
  Future<MatchModel> removePlayer({
    required int matchId,
    required int playerId,
  }) async {
    final response = await _client.delete(
      _uri('/api/matches/$matchId/players/$playerId'),
      headers: _headers(),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Rating tags of the match sport, used when the organizer finishes it.
  Future<List<RatingTagModel>> listRatingTags(int matchId) async {
    final response = await _client.get(
      _uri('/api/matches/$matchId/rating-tags'),
      headers: _headers(),
    );
    final body = _decode(response);
    final data = body['data'];

    if (data is! List) {
      throw const FormatException('The rating tags response has an invalid format');
    }

    return data
        .map((item) => RatingTagModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Marks a concluded match as finished. Ratings are optional.
  Future<MatchModel> finishMatch(
    int matchId, {
    List<Map<String, dynamic>> ratings = const [],
  }) async {
    final response = await _client.post(
      _uri('/api/matches/$matchId/finish'),
      headers: _headers(),
      body: jsonEncode({'ratings': ratings}),
    );
    final body = _decode(response);

    return MatchModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Soft-deletes a match created by the authenticated organizer.
  Future<void> deleteMatch(int matchId) async {
    final response = await _client.delete(
      _uri('/api/matches/$matchId'),
      headers: _headers(),
    );
    _decode(response);
  }

  /// Fetches the full detail of a single match (court gallery, players, etc.).
  Future<MatchModel> getMatch(int matchId) async {
    final response = await _client.get(
      _uri('/api/matches/$matchId'),
      headers: _headers(),
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

  Map<String, String> _headers({bool json = true}) {
    final token = _token;

    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
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