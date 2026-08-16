import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'account_remote_data_source.dart';

class AccountApi implements AccountRemoteDataSource {
  final Dio dio;

  AccountApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> updateProfile(FormData data) {
    return dio.post(ApiConstants.profileUpdate, data: data);
  }

  @override
  Future<Response<dynamic>> changePassword(Map<String, dynamic> data) {
    return dio.post(ApiConstants.passwordChange, data: data);
  }

  @override
  Future<Response<dynamic>> deactivateAccount(Map<String, dynamic> data) {
    return dio.post(
      ApiConstants.accountDeactivate,
      data: data,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> updatePharmacy(Map<String, dynamic> data) {
    return dio.post(ApiConstants.pharmacyProfileUpdate, data: data);
  }
}
