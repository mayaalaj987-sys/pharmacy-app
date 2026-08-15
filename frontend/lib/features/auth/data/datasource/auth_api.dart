import 'package:dio/dio.dart';

import '../../../../core/network/api_constants.dart';
import '../../../../core/network/dio_client.dart';
import 'auth_remote_datasource.dart';

class AuthApi implements AuthRemoteDataSource {
  final Dio dio;

  AuthApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> registerPharmacist(FormData data) {
    return dio.post(
      ApiConstants.registerPharmacist,
      data: data,
      options: Options(extra: {'skipAuthentication': true}),
    );
  }

  @override
  Future<Response<dynamic>> registerEmployee(FormData data) {
    return dio.post(
      ApiConstants.registerEmployee,
      data: data,
      options: Options(extra: {'skipAuthentication': true}),
    );
  }

  @override
  Future<Response<dynamic>> login(String email, String password) {
    return dio.post(
      ApiConstants.login,
      data: {'email': email, 'password': password},
      options: Options(extra: {'skipAuthentication': true}),
    );
  }

  @override
  Future<Response<dynamic>> employeeLogin(String email, String password) {
    return dio.post(
      ApiConstants.employeeLogin,
      data: {'email': email, 'password': password},
      options: Options(extra: {'skipAuthentication': true}),
    );
  }

  @override
  Future<Response<dynamic>> me({int? activePharmacyId}) {
    return dio.get(
      ApiConstants.me,
      options: Options(
        headers: activePharmacyId == null
            ? null
            : {'X-Pharmacy-Id': activePharmacyId},
      ),
    );
  }

  @override
  Future<Response<dynamic>> logout() {
    return dio.post(
      ApiConstants.logout,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }
}
