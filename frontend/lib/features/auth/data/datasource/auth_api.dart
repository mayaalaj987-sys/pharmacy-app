import 'package:dio/dio.dart';

import '../../../../core/network/dio_client.dart';
import '../../../../core/network/api_constants.dart';

class AuthApi {
  final Dio dio = DioClient.dio;

  Future<Response> registerPharmacist(FormData data) {
    return dio.post(ApiConstants.registerPharmacist, data: data);
  }

  Future<Response> registerPharmacy(FormData data) {
    return dio.post(ApiConstants.registerPharmacy, data: data);
  }

  Future<Response> login(String email, String password) {
    return dio.post(
      ApiConstants.login,
      data: {"email": email, "password": password},
    );
  }
}