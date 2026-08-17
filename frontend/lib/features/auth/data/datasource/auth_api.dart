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
  Future<Response<dynamic>> registrationStatus(String statusToken) {
    return dio.get(
      ApiConstants.registrationStatus,
      options: Options(
        headers: {'Authorization': 'Bearer $statusToken'},
        extra: {'skipAuthentication': true, 'skipActivePharmacy': true},
      ),
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

  /// The owner is derived from the bearer token server-side, so no actor or
  /// status field is ever sent. `skipActivePharmacy` keeps the currently
  /// selected pharmacy out of the request: this creates a new one.
  @override
  Future<Response<dynamic>> addPharmacy(FormData data) {
    return dio.post(
      ApiConstants.pharmacyAdd,
      data: data,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> logout() {
    return dio.post(
      ApiConstants.logout,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> logoutRegistrationStatus(String statusToken) {
    return dio.post(
      ApiConstants.logout,
      options: Options(
        headers: {'Authorization': 'Bearer $statusToken'},
        extra: {'skipAuthentication': true, 'skipActivePharmacy': true},
      ),
    );
  }
}
