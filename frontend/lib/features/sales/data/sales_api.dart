import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'sales_remote_data_source.dart';

class SalesApi implements SalesRemoteDataSource {
  final Dio dio;

  SalesApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> createSale(Map<String, dynamic> data) {
    return dio.post(ApiConstants.saleCreate, data: data);
  }

  @override
  Future<Response<dynamic>> getAllSales({String? filter}) {
    return dio.get(
      ApiConstants.saleAll,
      queryParameters: filter == null ? null : {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getDailySales() {
    return dio.get(ApiConstants.saleDaily);
  }
}
