import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
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

  /// [shift] claims a seat at the pharmacy — a shift can only be held by one
  /// person, so approving into a covered one is refused with `shift_taken`.
  /// Omitting it lets the backend pick the first free shift.
  Future<void> approveEmployee(int id, {double? salary, String? shift}) async {
    try {
      await api.approveEmployee(id, {'salary': ?salary, 'shift': ?shift});
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
}
