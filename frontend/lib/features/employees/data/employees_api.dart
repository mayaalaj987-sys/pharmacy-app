import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'employees_remote_data_source.dart';

class EmployeesApi implements EmployeesRemoteDataSource {
  final Dio dio;

  EmployeesApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getPendingEmployees() {
    return dio.get(ApiConstants.employeesPending);
  }

  @override
  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId) {
    return dio.get('${ApiConstants.employees}/$pharmacyId');
  }

  @override
  Future<Response<dynamic>> sendOffer(Map<String, dynamic> data) {
    return dio.post(ApiConstants.recruitmentOffers, data: data);
  }

  @override
  Future<Response<dynamic>> dismissEmployee(int id) {
    return dio.delete('${ApiConstants.employees}/$id/dismiss');
  }
}
