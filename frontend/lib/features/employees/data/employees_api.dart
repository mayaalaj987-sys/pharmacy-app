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

  @override
  Future<Response<dynamic>> getApplicantDocuments(int employeeId) {
    return dio.get('${ApiConstants.recruitmentApplicants}/$employeeId/documents');
  }

  @override
  Future<Response<List<int>>> downloadDocument(String url) {
    // The listing returns absolute URLs; Dio would otherwise prefix the base.
    return dio.get<List<int>>(
      url,
      options: Options(responseType: ResponseType.bytes),
    );
  }

  @override
  Future<Response<dynamic>> promoteEmployee(int id) {
    return dio.post('${ApiConstants.employees}/$id/promote');
  }
}
