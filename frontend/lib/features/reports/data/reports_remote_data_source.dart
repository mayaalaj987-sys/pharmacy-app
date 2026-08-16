import 'package:dio/dio.dart';

abstract class ReportsRemoteDataSource {
  Future<Response<dynamic>> getDashboard();

  Future<Response<dynamic>> getRevenue(String filter);

  Future<Response<dynamic>> getProfits(String filter);

  Future<Response<dynamic>> getInventoryValue();

  Future<Response<dynamic>> getMostSold(String filter);
}
