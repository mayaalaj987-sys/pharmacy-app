import 'package:dio/dio.dart';

abstract class EmployeeWorkspaceRemoteDataSource {
  Future<Response<dynamic>> getMyTasks();

  Future<Response<dynamic>> markTaskDone(int taskId);

  Future<Response<dynamic>> getMySales(int employeeId);

  Future<Response<dynamic>> updateProfile(Map<String, dynamic> data);

  Future<Response<dynamic>> changePassword(Map<String, dynamic> data);
}
