import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/tournament_model.dart';

class TournamentApiException implements Exception {
  const TournamentApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'TournamentApiException($statusCode): $message';
}

class TournamentApiService {
  TournamentApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;
  final String? _token;
  final http.Client _client;

  /// [scope]: `open` (taking registrations), `upcoming` (not finished, default) or `finished`.
  Future<List<TournamentModel>> list({String scope = 'upcoming', int? sportId}) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/tournaments').replace(queryParameters: {
        'scope': scope,
        if (sportId != null) 'sport_id': '$sportId',
      }),
      headers: _headers(),
    );
    return (_decode(response)['data'] as List<dynamic>)
        .map((item) => TournamentModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<TournamentModel> show(int id) async {
    final response = await _client.get(Uri.parse('$_baseUrl/api/tournaments/$id'), headers: _headers());
    return TournamentModel.fromJson(_decode(response)['data'] as Map<String, dynamic>);
  }

  /// Tournaments where a team of the user is registered.
  Future<List<MyTournamentEntry>> mine() async {
    final response = await _client.get(Uri.parse('$_baseUrl/api/tournaments/mine'), headers: _headers());
    return (_decode(response)['data'] as List<dynamic>)
        .map((item) => MyTournamentEntry.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<TournamentRegistrationModel> register(int tournamentId, int teamId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/tournaments/$tournamentId/registrations'),
      headers: _headers(),
      body: jsonEncode({'team_id': teamId}),
    );
    return TournamentRegistrationModel.fromJson(_decode(response)['data'] as Map<String, dynamic>);
  }

  /// Simulated QR payment of the entry fee.
  Future<TournamentRegistrationModel> pay(int registrationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/tournament-registrations/$registrationId/pay'),
      headers: _headers(),
    );
    return TournamentRegistrationModel.fromJson(_decode(response)['data'] as Map<String, dynamic>);
  }

  Future<void> cancel(int registrationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/tournament-registrations/$registrationId/cancel'),
      headers: _headers(),
    );
    _decode(response);
  }

  void dispose() => _client.close();

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = decoded['errors'];
      final firstError = errors is Map && errors.values.isNotEmpty ? (errors.values.first as List).first as String : null;
      throw TournamentApiException(
        firstError ?? decoded['message'] as String? ?? 'No pudimos completar la operación.',
        response.statusCode,
      );
    }
    return decoded;
  }

  Map<String, String> _headers() {
    final token = _token;
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }
}
