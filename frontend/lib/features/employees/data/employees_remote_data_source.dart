import 'package:dio/dio.dart';

abstract class EmployeesRemoteDataSource {
  Future<Response<dynamic>> getPendingEmployees();

  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId);

  /// Offers an applicant a shift. Hiring needs their answer now, so this
  /// replaced the approve call that attached them outright.
  Future<Response<dynamic>> sendOffer(Map<String, dynamic> data);

  /// Confirms a trainee finished their training. Only a pharmacy that employed
  /// them can say so, so this is the pharmacist's call rather than the
  /// trainee's — `role` is prohibited on their own profile for that reason.
  Future<Response<dynamic>> promoteEmployee(int id);

  /// Lists an applicant's current documents. You cannot offer a stranger a
  /// salary on the strength of a name, so this is what an offer rests on.
  Future<Response<dynamic>> getApplicantDocuments(int employeeId);

  /// Fetches the bytes over the authenticated client. The URLs carry a bearer
  /// token requirement, so they cannot simply be handed to a browser.
  Future<Response<List<int>>> downloadDocument(String url);

  Future<Response<dynamic>> dismissEmployee(int id);
}
