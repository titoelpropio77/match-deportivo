import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/court_field_model.dart';
import '../models/court_model.dart';
import '../models/featured_court_model.dart';
import '../models/rental_item_model.dart';

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

  /// Sports centers for the home: best rated, then new ones; any center when none qualifies.
  Future<List<FeaturedCourtModel>> featured({int? cityId, int limit = 10}) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/courts/featured').replace(
        queryParameters: {
          'limit': limit.toString(),
          if (cityId != null) 'city_id': cityId.toString(),
        },
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
      throw const FormatException('The featured courts response has an invalid format');
    }

    return data
        .map((item) => FeaturedCourtModel.fromJson(item as Map<String, dynamic>))
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

  /// Active gear the sports center rents with its courts (balls, rackets...).
  Future<List<RentalItemModel>> rentalItems(int courtId) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/courts/$courtId/rental-items'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => RentalItemModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Books every item at once (all or nothing); they are paid together with one QR.
  Future<CourtBookingModel> createBooking(List<BookingItem> items) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-bookings'),
      headers: _headers(),
      body: jsonEncode({'items': items.map((item) => item.toJson()).toList()}),
    );
    final decoded = _decode(response);
    return CourtBookingModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  /// Simulated QR payment of a whole booking.
  Future<CourtBookingModel> payBooking(String code) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-bookings/$code/pay'),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return CourtBookingModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  /// Releases every unpaid range of a booking.
  Future<void> cancelBooking(String code) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/court-bookings/$code/cancel'),
      headers: _headers(),
    );
    _decode(response);
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
