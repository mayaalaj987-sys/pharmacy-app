import 'package:dio/dio.dart';

abstract class OrdersRemoteDataSource {
  Future<Response<dynamic>> getOrders();

  Future<Response<dynamic>> getReceivingPlan(int id);

  Future<Response<dynamic>> receiveOrder(int id, Map<String, dynamic> data);

  Future<Response<dynamic>> cancelOrder(int id);
}
