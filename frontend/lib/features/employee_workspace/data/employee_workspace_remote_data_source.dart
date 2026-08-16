import 'package:dio/dio.dart';

abstract class EmployeeWorkspaceRemoteDataSource {
  Future<Response<dynamic>> getMyTasks();

  Future<Response<dynamic>> markTaskDone(int taskId);

  Future<Response<dynamic>> getMySales(int employeeId);
}
