import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/event_space_model.dart';

class EventSpaceApiException implements Exception {
  const EventSpaceApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'EventSpaceApiException($statusCode): $message';
}

/// Event spaces of sports centers (grill areas, halls...) and their reservations.
class EventSpaceApiService {
  EventSpaceApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;
  final String? _token;
  final http.Client _client;

  /// Active spaces, optionally of one sports center or fitting [guests] people.
  Future<List<EventSpaceModel>> list({int? courtId, int? guests}) async {
    final query = <String, String>{
      if (courtId != null) 'court_id': '$courtId',
      if (guests != null) 'guests': '$guests',
    };
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/event-spaces').replace(queryParameters: query.isEmpty ? null : query),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => EventSpaceModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<EventSpaceAvailabilityModel> availability({required int spaceId, required DateTime date}) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/event-spaces/$spaceId/availability').replace(
        queryParameters: {'date': _formatDate(date)},
      ),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return EventSpaceAvailabilityModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  /// Holds the hours as pending payment until [pay] (simulated QR).
  Future<EventSpaceReservationModel> reserve({
    required int spaceId,
    required DateTime date,
    required String startTime,
    required int hours,
    required int guests,
    String? eventType,
    String? notes,
  }) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/event-space-reservations'),
      headers: _headers(),
      body: jsonEncode({
        'event_space_id': spaceId,
        'date': _formatDate(date),
        'start_time': startTime,
        'hours': hours,
        'guests': guests,
        'event_type': ?eventType,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      }),
    );
    final decoded = _decode(response);
    return EventSpaceReservationModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  Future<EventSpaceReservationModel> pay(int reservationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/event-space-reservations/$reservationId/pay'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return EventSpaceReservationModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  Future<void> cancel(int reservationId) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/event-space-reservations/$reservationId/cancel'),
      headers: _headers(),
    );
    _decode(response);
  }

  /// Event reservations of the signed-in user, upcoming first.
  Future<List<EventSpaceReservationModel>> myReservations() async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/event-space-reservations'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => EventSpaceReservationModel.fromJson(item as Map<String, dynamic>))
        .toList();
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
      throw EventSpaceApiException(
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

  Map<String, String> _headers() {
    final token = _token;

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }
}
