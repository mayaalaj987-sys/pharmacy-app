import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/applicant_document.dart';
import '../domain/employee.dart';
import 'employees_remote_data_source.dart';

class EmployeesRepository {
  final EmployeesRemoteDataSource api;

  const EmployeesRepository(this.api);

  /// Intentionally global on the backend: every unassigned pending request,
  /// not only requests aimed at this pharmacy.
  Future<List<Employee>> fetchPendingEmployees() async {
    try {
      return _parse(await api.getPendingEmployees());
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<List<Employee>> fetchPharmacyEmployees(int pharmacyId) async {
    try {
      return _parse(await api.getPharmacyEmployees(pharmacyId));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Offers [id] the named [shift]. The applicant decides from here; nothing
  /// about their employment changes until they accept.
  Future<void> sendOffer(int id, {required String shift, double? salary}) async {
    try {
      await api.sendOffer({
        'employee_id': id,
        'shift': shift,
        'salary': ?salary,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> dismissEmployee(int id) async {
    try {
      await api.dismissEmployee(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  List<Employee> _parse(Response<dynamic> response) {
    final data = response.data;
    final raw = data is Map<String, dynamic> ? data['employees'] : null;
    if (raw is! List) return const <Employee>[];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(Employee.fromJson)
        .toList(growable: false);
  }

  Future<List<ApplicantDocument>> fetchApplicantDocuments(int employeeId) async {
    try {
      final response = await api.getApplicantDocuments(employeeId);
      final data = response.data;
      if (data is! Map<String, dynamic>) return const <ApplicantDocument>[];
      final raw = data['data'];

      return raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(ApplicantDocument.fromJson)
                .toList(growable: false)
          : const <ApplicantDocument>[];
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<List<int>> downloadDocument(String url) async {
    try {
      return (await api.downloadDocument(url)).data ?? const <int>[];
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
