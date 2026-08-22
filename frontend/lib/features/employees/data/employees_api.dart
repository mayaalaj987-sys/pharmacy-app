import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'employees_remote_data_source.dart';

class EmployeesApi implements EmployeesRemoteDataSource {
  final Dio dio;

  EmployeesApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getPendingEmployees() async {
    const perPage = 100;
    final employees = <dynamic>[];
    late Response<dynamic> response;
    var page = 1;
    var lastPage = 1;

    // The backend caps pages at 100. Fetch every page here so the UI never
    // silently hides applicant 26 (or 101) while keeping pagination details
    // out of every screen and cubit.
    do {
      response = await dio.get(
        ApiConstants.employeesPending,
        queryParameters: {'page': page, 'per_page': perPage},
      );
      final body = response.data;
      if (body is! Map<String, dynamic>) break;

      final pageEmployees = body['employees'];
      if (pageEmployees is List) employees.addAll(pageEmployees);

      final meta = body['meta'];
      lastPage = meta is Map
          ? int.tryParse(meta['last_page']?.toString() ?? '') ?? page
          : page;
      page++;
    } while (page <= lastPage);

    final lastResponse = response;

    final body = lastResponse.data is Map<String, dynamic>
        ? Map<String, dynamic>.from(lastResponse.data as Map<String, dynamic>)
        : <String, dynamic>{};
    body['employees'] = employees;
    body['count'] = employees.length;

    return Response<dynamic>(
      requestOptions: lastResponse.requestOptions,
      data: body,
      statusCode: lastResponse.statusCode,
      statusMessage: lastResponse.statusMessage,
      headers: lastResponse.headers,
      redirects: lastResponse.redirects,
      extra: lastResponse.extra,
    );
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
  Future<Response<dynamic>> getOffers() {
    return dio.get(ApiConstants.recruitmentOffers);
  }

  @override
  Future<Response<dynamic>> withdrawOffer(int offerId) {
    return dio.delete('${ApiConstants.recruitmentOffers}/$offerId');
  }

  @override
  Future<Response<dynamic>> dismissEmployee(int id) {
    return dio.delete('${ApiConstants.employees}/$id/dismiss');
  }

  @override
  Future<Response<dynamic>> getApplicantDocuments(int employeeId) {
    return dio.get(
      '${ApiConstants.recruitmentApplicants}/$employeeId/documents',
    );
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
