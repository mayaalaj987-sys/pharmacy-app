import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'purchase_cart_remote_data_source.dart';

class PurchaseCartApi implements PurchaseCartRemoteDataSource {
  final Dio dio;

  PurchaseCartApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getCart() => dio.get(ApiConstants.purchaseCart);

  @override
  Future<Response<dynamic>> addItem(int medicineId, int quantity) {
    return dio.post(
      ApiConstants.purchaseCart,
      data: {'medicine_id': medicineId, 'quantity': quantity},
    );
  }

  @override
  Future<Response<dynamic>> setQuantity(int itemId, int quantity) {
    return dio.patch(
      '${ApiConstants.purchaseCart}/$itemId',
      data: {'quantity': quantity},
    );
  }

  @override
  Future<Response<dynamic>> removeItem(int itemId) {
    return dio.delete('${ApiConstants.purchaseCart}/$itemId');
  }

  @override
  Future<Response<dynamic>> clear() => dio.delete(ApiConstants.purchaseCart);

  @override
  Future<Response<dynamic>> switchSupplier(int itemId, int medicineId) {
    return dio.post(
      '${ApiConstants.purchaseCart}/$itemId/switch-supplier',
      data: {'medicine_id': medicineId},
    );
  }

  @override
  Future<Response<dynamic>> checkout(String paymentMethod, String? cardNumber) {
    return dio.post(
      ApiConstants.purchaseCartCheckout,
      data: {'payment_method': paymentMethod, 'card_number': ?cardNumber},
    );
  }
}
