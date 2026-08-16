import 'package:dio/dio.dart';

abstract class SalesRemoteDataSource {
  Future<Response<dynamic>> createSale(Map<String, dynamic> data);

  Future<Response<dynamic>> getAllSales({String? filter});

  Future<Response<dynamic>> getDailySales();
}
