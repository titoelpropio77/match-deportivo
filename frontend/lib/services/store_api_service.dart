import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/store_model.dart';

class StoreApiException implements Exception {
  const StoreApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  @override
  String toString() => 'StoreApiException($statusCode): $message';
}

/// Stores of the sports centers, their products and the user's purchases.
class StoreApiService {
  StoreApiService({
    required String baseUrl,
    this._token,
    http.Client? client,
  })  : _baseUrl = baseUrl.replaceFirst(RegExp(r'/$'), ''),
        _client = client ?? http.Client();

  final String _baseUrl;
  final String? _token;
  final http.Client _client;

  /// Product categories with how many active stores sell them.
  Future<List<ProductCategoryModel>> categories() async {
    final response = await _client.get(Uri.parse('$_baseUrl/api/product-categories'), headers: _headers());
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => ProductCategoryModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Active stores, optionally of one category or matching [search] (store name).
  Future<List<StoreModel>> stores({int? categoryId, String? search}) async {
    final query = <String, String>{
      if (categoryId != null) 'category_id': '$categoryId',
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    };
    final response = await _client.get(
      Uri.parse('$_baseUrl/api/stores').replace(queryParameters: query.isEmpty ? null : query),
      headers: _headers(),
    );
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => StoreModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<ProductModel>> products(int storeId) async {
    final response = await _client.get(Uri.parse('$_baseUrl/api/stores/$storeId/products'), headers: _headers());
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => ProductModel.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Holds the units as pending payment until [pay] (simulated QR).
  Future<StoreOrderModel> placeOrder({required int storeId, required List<CartLine> lines, String? notes}) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/store-orders'),
      headers: _headers(),
      body: jsonEncode({
        'store_id': storeId,
        'items': [
          for (final line in lines) {'product_id': line.product.id, 'quantity': line.quantity},
        ],
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      }),
    );
    final decoded = _decode(response);
    return StoreOrderModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  Future<StoreOrderModel> pay(int orderId) async {
    final response = await _client.post(Uri.parse('$_baseUrl/api/store-orders/$orderId/pay'), headers: _headers());
    final decoded = _decode(response);
    return StoreOrderModel.fromJson(decoded['data'] as Map<String, dynamic>);
  }

  Future<void> cancel(int orderId) async {
    final response = await _client.post(Uri.parse('$_baseUrl/api/store-orders/$orderId/cancel'), headers: _headers());
    _decode(response);
  }

  /// Purchases of the signed-in user, newest first.
  Future<List<StoreOrderModel>> myOrders() async {
    final response = await _client.get(Uri.parse('$_baseUrl/api/store-orders'), headers: _headers());
    final decoded = _decode(response);
    return (decoded['data'] as List)
        .map((item) => StoreOrderModel.fromJson(item as Map<String, dynamic>))
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
      throw StoreApiException(
        firstError ?? decoded['message'] as String? ?? 'The API request failed',
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
