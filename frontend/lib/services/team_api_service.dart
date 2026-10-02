import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/team_model.dart';

class TeamApiException implements Exception {
  const TeamApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'TeamApiException($statusCode): $message';
}

/// Logo picked from the gallery: a file path (mobile) or bytes (web/desktop).
class TeamLogoUpload {
  const TeamLogoUpload({this.path, this.bytes, this.filename});

  final String? path;
  final List<int>? bytes;
  final String? filename;
}

class TeamApiService {
  TeamApiService({
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

  /// Teams the signed-in user plays in.
  Future<List<TeamModel>> myTeams({int? sportId}) async {
    final response = await _client.get(
      _uri('/api/teams', {if (sportId != null) 'sport_id': '$sportId'}),
      headers: _headers(),
    );
    return _list(_decode(response));
  }

  /// Any team by name or abbreviation.
  Future<List<TeamModel>> search(String query, {int? sportId}) async {
    final response = await _client.get(
      _uri('/api/teams/search', {
        if (query.trim().isNotEmpty) 'query': query.trim(),
        if (sportId != null) 'sport_id': '$sportId',
      }),
      headers: _headers(),
    );
    return _list(_decode(response));
  }

  Future<TeamModel> show(int id) async {
    final response = await _client.get(_uri('/api/teams/$id'), headers: _headers());
    return _team(_decode(response));
  }

  Future<TeamModel> create({
    required String name,
    required int sportId,
    String? shortName,
    int? levelId,
    String gender = 'mixed',
    String? primaryColor,
    String? description,
    List<int> memberIds = const [],
    TeamLogoUpload? logo,
  }) {
    return _sendTeam('/api/teams', {
      'name': name,
      'sport_id': '$sportId',
      'short_name': shortName ?? '',
      'level_id': levelId?.toString() ?? '',
      'gender': gender,
      'primary_color': primaryColor ?? '',
      'description': description ?? '',
      for (var index = 0; index < memberIds.length; index++) 'member_ids[$index]': '${memberIds[index]}',
    }, logo);
  }

  Future<TeamModel> update(
    int id, {
    required String name,
    required int sportId,
    String? shortName,
    int? levelId,
    String gender = 'mixed',
    String? primaryColor,
    String? description,
    TeamLogoUpload? logo,
    bool removeLogo = false,
  }) {
    return _sendTeam('/api/teams/$id', {
      'name': name,
      'sport_id': '$sportId',
      'short_name': shortName ?? '',
      'level_id': levelId?.toString() ?? '',
      'gender': gender,
      'primary_color': primaryColor ?? '',
      'description': description ?? '',
      if (removeLogo) 'remove_logo': '1',
    }, logo);
  }

  Future<void> delete(int id) async {
    final response = await _client.delete(_uri('/api/teams/$id'), headers: _headers());
    _decode(response);
  }

  Future<TeamModel> addMember(int teamId, int userId, {int? jerseyNumber, String? position}) async {
    final response = await _client.post(
      _uri('/api/teams/$teamId/members'),
      headers: _headers(),
      body: jsonEncode({
        'user_id': userId,
        'jersey_number': ?jerseyNumber,
        if (position != null && position.trim().isNotEmpty) 'position': position.trim(),
      }),
    );
    return _team(_decode(response));
  }

  Future<TeamModel> updateMember(int teamId, int userId, {int? jerseyNumber, String? position}) async {
    final response = await _client.patch(
      _uri('/api/teams/$teamId/members/$userId'),
      headers: _headers(),
      body: jsonEncode({
        'jersey_number': jerseyNumber,
        'position': (position == null || position.trim().isEmpty) ? null : position.trim(),
      }),
    );
    return _team(_decode(response));
  }

  /// Captain removing a member, or a member leaving the team.
  Future<TeamModel> removeMember(int teamId, int userId) async {
    final response = await _client.delete(_uri('/api/teams/$teamId/members/$userId'), headers: _headers());
    return _team(_decode(response));
  }

  void dispose() => _client.close();

  Future<TeamModel> _sendTeam(String path, Map<String, String> fields, TeamLogoUpload? logo) async {
    final request = http.MultipartRequest('POST', _uri(path));
    request.headers.addAll(_headers(json: false));
    // Empty optional fields are sent as "" so an edit can clear them (the API treats "" as null).
    request.fields.addAll(fields);

    if (logo?.path != null && logo!.path!.isNotEmpty) {
      request.files.add(await http.MultipartFile.fromPath('logo', logo.path!, filename: logo.filename));
    } else if (logo?.bytes != null && logo!.bytes!.isNotEmpty) {
      request.files.add(http.MultipartFile.fromBytes('logo', logo.bytes!, filename: logo.filename ?? 'logo.jpg'));
    }

    final response = await http.Response.fromStream(await _client.send(request));
    return _team(_decode(response));
  }

  Uri _uri(String path, [Map<String, String>? query]) =>
      Uri.parse('$_baseUrl$path').replace(queryParameters: (query == null || query.isEmpty) ? null : query);

  List<TeamModel> _list(Map<String, dynamic> decoded) => (decoded['data'] as List<dynamic>)
      .map((item) => TeamModel.fromJson(item as Map<String, dynamic>))
      .toList();

  TeamModel _team(Map<String, dynamic> decoded) => TeamModel.fromJson(decoded['data'] as Map<String, dynamic>);

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = decoded['errors'];
      final firstError = errors is Map && errors.values.isNotEmpty ? (errors.values.first as List).first as String : null;
      throw TeamApiException(
        firstError ?? decoded['message'] as String? ?? 'No pudimos completar la operación.',
        response.statusCode,
      );
    }
    return decoded;
  }

  Map<String, String> _headers({bool json = true}) {
    final token = _token;
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }
}
