import 'package:dio/dio.dart';

abstract class EmployeesRemoteDataSource {
  Future<Response<dynamic>> getPendingEmployees();

  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId);

  /// Offers an applicant a shift. Hiring needs their answer now, so this
  /// replaced the approve call that attached them outright.
  Future<Response<dynamic>> sendOffer(Map<String, dynamic> data);

  Future<Response<dynamic>> dismissEmployee(int id);
}
