import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/purchase_cart.dart';
import 'purchase_cart_remote_data_source.dart';

/// Every mutation answers with the whole cart, so every method here returns one.
///
/// Quantities interact: a line's cheaper-elsewhere verdict depends on what the
/// supplier still holds, and totals depend on every line. Patching one row
/// locally would leave the screen showing a cart that never existed.
class PurchaseCartRepository {
  final PurchaseCartRemoteDataSource api;

  const PurchaseCartRepository(this.api);

  Future<PurchaseCart> fetch() => _cart(() => api.getCart());

  Future<PurchaseCart> add(int medicineId, int quantity) =>
      _cart(() => api.addItem(medicineId, quantity));

  Future<PurchaseCart> setQuantity(int itemId, int quantity) =>
      _cart(() => api.setQuantity(itemId, quantity));

  Future<PurchaseCart> remove(int itemId) =>
      _cart(() => api.removeItem(itemId));

  Future<PurchaseCart> clear() => _cart(() => api.clear());

  Future<PurchaseCart> switchSupplier(int itemId, int medicineId) =>
      _cart(() => api.switchSupplier(itemId, medicineId));

  /// Buys the cart. Returns how many orders it became — one per supplier.
  ///
  /// [cardNumber] is required by the server when paying by card, exactly as the
  /// till requires it from a customer. It is validated and discarded; nothing
  /// stores it.
  Future<int> checkout(String paymentMethod, {String? cardNumber}) async {
    try {
      final response = await api.checkout(paymentMethod, cardNumber);
      final data = response.data;
      final orders = data is Map<String, dynamic> ? data['orders'] : null;

      return orders is List ? orders.length : 0;
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<PurchaseCart> _cart(Future<Response<dynamic>> Function() call) async {
    try {
      final response = await call();
      final data = response.data;

      return data is Map<String, dynamic>
          ? PurchaseCart.fromJson(data)
          : const PurchaseCart.empty();
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
