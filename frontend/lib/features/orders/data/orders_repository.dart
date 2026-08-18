import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/purchase_order.dart';
import '../domain/receiving_plan.dart';
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

  /// What is about to arrive, and what the pharmacy currently sells it for.
  Future<ReceivingPlan> fetchReceivingPlan(int id) async {
    try {
      final response = await api.getReceivingPlan(id);
      final data = response.data;

      return data is Map<String, dynamic>
          ? ReceivingPlan.fromJson(data)
          : const ReceivingPlan(supplierName: '', totalPrice: 0, items: []);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Takes the delivery into stock.
  ///
  /// [sellingPrices] is keyed by catalogue medicine id, as the receiving plan
  /// reports it. Leaving it out keeps whatever price each drug already has —
  /// which is right for a restock and falls back to the supplier's suggestion
  /// for anything new.
  Future<void> receiveOrder(int id, {Map<int, double>? sellingPrices}) async {
    try {
      await api.receiveOrder(id, {
        if (sellingPrices != null && sellingPrices.isNotEmpty)
          'selling_prices': sellingPrices.map(
            (medicineId, price) => MapEntry(medicineId.toString(), price),
          ),
      });
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
