import 'package:dio/dio.dart';

abstract class EmployeeOffersRemoteDataSource {
  Future<Response<dynamic>> getOffers();
}
