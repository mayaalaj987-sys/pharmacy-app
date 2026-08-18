import 'package:dio/dio.dart';

abstract class EmployeesRemoteDataSource {
  Future<Response<dynamic>> getPendingEmployees();

  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId);

  /// Offers an applicant a shift. Hiring needs their answer now, so this
  /// replaced the approve call that attached them outright.
  Future<Response<dynamic>> sendOffer(Map<String, dynamic> data);

  /// Lists an applicant's current documents. You cannot offer a stranger a
  /// salary on the strength of a name, so this is what an offer rests on.
  Future<Response<dynamic>> getApplicantDocuments(int employeeId);

  /// Fetches the bytes over the authenticated client. The URLs carry a bearer
  /// token requirement, so they cannot simply be handed to a browser.
  Future<Response<List<int>>> downloadDocument(String url);

  Future<Response<dynamic>> dismissEmployee(int id);
}
