import 'package:dio/dio.dart';

abstract class EmployeesRemoteDataSource {
  Future<Response<dynamic>> getPendingEmployees();

  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId);

  Future<Response<dynamic>> approveEmployee(int id, Map<String, dynamic> data);

  Future<Response<dynamic>> dismissEmployee(int id);
}
