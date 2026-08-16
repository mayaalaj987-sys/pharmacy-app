import 'package:dio/dio.dart';

abstract class OrdersRemoteDataSource {
  Future<Response<dynamic>> getOrders();

  Future<Response<dynamic>> createOrder(Map<String, dynamic> data);

  Future<Response<dynamic>> receiveOrder(int id);

  Future<Response<dynamic>> cancelOrder(int id);
}
