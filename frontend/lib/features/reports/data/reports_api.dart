import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'reports_remote_data_source.dart';

class ReportsApi implements ReportsRemoteDataSource {
  final Dio dio;

  ReportsApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getDashboard() {
    return dio.get(ApiConstants.reportsDashboard);
  }

  @override
  Future<Response<dynamic>> getRevenue(String filter) {
    return dio.get(
      ApiConstants.reportsRevenue,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getProfits(String filter) {
    return dio.get(
      ApiConstants.reportsProfits,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getInventoryValue() {
    return dio.get(ApiConstants.reportsInventoryValue);
  }

  @override
  Future<Response<dynamic>> getMostSold(String filter) {
    return dio.get(
      ApiConstants.reportsMostSold,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getMostSoldByCategory(String filter) {
    return dio.get(
      ApiConstants.reportsMostSoldCategory,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getAverageSales(String filter) {
    return dio.get(
      ApiConstants.reportsAverageSales,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getCashFlow(String filter) {
    return dio.get(
      ApiConstants.reportsCashFlow,
      queryParameters: {'filter': filter},
    );
  }

  @override
  Future<Response<dynamic>> getPaymentMethods(String filter) {
    return dio.get(
      ApiConstants.reportsPaymentMethods,
      queryParameters: {'filter': filter},
    );
  }
}
