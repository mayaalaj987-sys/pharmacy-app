import 'package:dio/dio.dart';

abstract class AccountRemoteDataSource {
  Future<Response<dynamic>> updateProfile(FormData data);

  Future<Response<dynamic>> changePassword(Map<String, dynamic> data);

  Future<Response<dynamic>> deactivateAccount(Map<String, dynamic> data);

  Future<Response<dynamic>> updatePharmacy(Map<String, dynamic> data);
}
