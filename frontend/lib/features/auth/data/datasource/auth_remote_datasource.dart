import 'package:dio/dio.dart';

abstract class AuthRemoteDataSource {
  Future<Response<dynamic>> registerPharmacist(FormData data);

  Future<Response<dynamic>> registerEmployee(FormData data);

  Future<Response<dynamic>> login(String email, String password);

  Future<Response<dynamic>> employeeLogin(String email, String password);

  Future<Response<dynamic>> me({int? activePharmacyId});

  Future<Response<dynamic>> logout();
}
