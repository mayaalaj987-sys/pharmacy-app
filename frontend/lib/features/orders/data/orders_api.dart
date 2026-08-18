import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'orders_remote_data_source.dart';

class OrdersApi implements OrdersRemoteDataSource {
  final Dio dio;

  OrdersApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getOrders() {
    return dio.get(ApiConstants.orders);
  }

  @override
  Future<Response<dynamic>> getReceivingPlan(int id) {
    return dio.get('${ApiConstants.orders}/$id/receiving-plan');
  }

  @override
  Future<Response<dynamic>> receiveOrder(int id, Map<String, dynamic> data) {
    return dio.post('${ApiConstants.orders}/$id/receive', data: data);
  }

  @override
  Future<Response<dynamic>> cancelOrder(int id) {
    return dio.post('${ApiConstants.orders}/$id/cancel');
  }
}
