import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/purchase_order.dart';
import 'orders_remote_data_source.dart';

class OrdersRepository {
  final OrdersRemoteDataSource api;

  const OrdersRepository(this.api);

  Future<List<PurchaseOrder>> fetchOrders() async {
    try {
      final response = await api.getOrders();
      final data = response.data;
      final raw = data is Map<String, dynamic> ? data['orders'] : null;
      if (raw is! List) return const <PurchaseOrder>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(PurchaseOrder.fromJson)
          .toList(growable: false);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Creates a supplier order. [items] entries are `{medicine_id, quantity}`.
  Future<void> createOrder({
    required int supplierId,
    required List<Map<String, dynamic>> items,
    String paymentMethod = 'cash',
  }) async {
    try {
      await api.createOrder({
        'supplier_id': supplierId,
        'payment_method': paymentMethod,
        'items': items,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> receiveOrder(int id) async {
    try {
      await api.receiveOrder(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> cancelOrder(int id) async {
    try {
      await api.cancelOrder(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
