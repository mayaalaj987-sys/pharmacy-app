import 'package:dio/dio.dart';

abstract class PurchaseCartRemoteDataSource {
  Future<Response<dynamic>> getCart();

  Future<Response<dynamic>> addItem(int medicineId, int quantity);

  Future<Response<dynamic>> setQuantity(int itemId, int quantity);

  Future<Response<dynamic>> removeItem(int itemId);

  Future<Response<dynamic>> clear();

  Future<Response<dynamic>> switchSupplier(int itemId, int medicineId);

  Future<Response<dynamic>> checkout(String paymentMethod);
}
