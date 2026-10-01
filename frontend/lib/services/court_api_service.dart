import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/court_field_model.dart';
import '../models/court_model.dart';

class CourtApiException implements Exception {
  const CourtApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'CourtApiException($statusCode): $message';
}

class CourtApiService {
  CourtApiService({
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

  /// Sports centers; [search] filters by name, address or city on the server.
  Future<List<CourtModel>> list({String? search}) async {
    final query = <String, String>{
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    };
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/courts').replace(
        queryParameters: query.isEmpty ? null : query,
      ),
      headers: _headers(),
    );
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw CourtApiException(
        decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    final data = decoded['data'];
    if (data is! List) {
      throw const FormatException('The courts response has an invalid format');
    }

    return data
        .map((item) => CourtModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<CourtFieldModel>> listFields({int? sportId, DateTime? date}) async {
    final query = <String, String>{
      if (sportId != null) 'sport_id': '$sportId',
      if (date != null) 'date': _formatDate(date),
    };
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/court-fields').replace(queryParameters: query),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => CourtFieldModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<CourtAvailabilityModel> availability({
    required int fieldId,
    required DateTime date,
  }) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/court-fields/$fieldId/availability').replace(
        queryParameters: {'date': _formatDate(date)},
      ),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return CourtAvailabilityModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  Future<CourtReservationModel> reserve({
    required int fieldId,
    required int sportId,
    required DateTime date,
    required String startTime,
    required int hours,
  }) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-reservations'),
      headers: _headers(),
      body: jsonEncode({
        'court_field_id': fieldId,
        'sport_id': sportId,
        'date': _formatDate(date),
        'start_time': startTime,
        'hours': hours,
      }),
    );
    final decoded = _decode(response);
    return CourtReservationModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  /// Reservations of the signed-in user, upcoming first.
  Future<List<CourtReservationModel>> myReservations() async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/court-reservations'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => CourtReservationModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Simulated QR payment; the backend marks the reservation as paid.
  Future<CourtReservationModel> pay(int reservationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-reservations/$reservationId/pay'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return CourtReservationModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  /// Releases an unpaid reservation so the hour becomes free again.
  Future<void> cancelReservation(int reservationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-reservations/$reservationId/cancel'),
      headers: _headers(),
    );
    _decode(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = decoded['errors'];
      final firstError = errors is Map && errors.values.isNotEmpty
          ? (errors.values.first as List).first as String
          : null;
      throw CourtApiException(
        firstError ?? decoded['message'] as String? ?? 'The API request failed',
        response.statusCode,
      );
    }

    return decoded;
  }

  String _formatDate(DateTime date) {
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
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
