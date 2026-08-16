import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/sale.dart';
import 'sales_remote_data_source.dart';

/// Aggregate returned by the sales list endpoints.
class SalesSummary {
  final int totalSales;
  final double totalPrice;
  final List<Sale> sales;

  const SalesSummary({
    required this.totalSales,
    required this.totalPrice,
    required this.sales,
  });
}

class SalesRepository {
  final SalesRemoteDataSource api;

  const SalesRepository(this.api);

  /// Creates a sale. [items] entries are `{medicine_id, quantity}`.
  /// Actor ids are never sent: the backend derives them from the token.
  Future<void> createSale({
    required List<Map<String, dynamic>> items,
    required String paymentMethod,
    String? customerName,
    String? cardNumber,
  }) async {
    try {
      await api.createSale({
        'payment_method': paymentMethod,
        'items': items,
        if (customerName != null && customerName.trim().isNotEmpty)
          'customer_name': customerName.trim(),
        if (paymentMethod == 'card' && cardNumber != null)
          'card_number': cardNumber,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<SalesSummary> fetchAllSales({String? filter}) async {
    try {
      return _parse(await api.getAllSales(filter: filter));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<SalesSummary> fetchDailySales() async {
    try {
      return _parse(await api.getDailySales());
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  SalesSummary _parse(Response<dynamic> response) {
    final data = response.data;
    if (data is! Map<String, dynamic>) {
      return const SalesSummary(totalSales: 0, totalPrice: 0, sales: []);
    }
    final raw = data['sales'];
    final sales = raw is List
        ? raw.whereType<Map<String, dynamic>>().map(Sale.fromJson).toList(
            growable: false,
          )
        : const <Sale>[];

    return SalesSummary(
      totalSales: data['total_sales'] is num
          ? (data['total_sales'] as num).toInt()
          : sales.length,
      totalPrice: data['total_price'] is num
          ? (data['total_price'] as num).toDouble()
          : double.tryParse(data['total_price']?.toString() ?? '') ?? 0,
      sales: sales,
    );
  }
}
