import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'employee_workspace_remote_data_source.dart';

class EmployeeWorkspaceApi implements EmployeeWorkspaceRemoteDataSource {
  final Dio dio;

  EmployeeWorkspaceApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getMyTasks() {
    return dio.get(ApiConstants.tasks);
  }

  @override
  Future<Response<dynamic>> markTaskDone(int taskId) {
    return dio.post('${ApiConstants.tasks}/$taskId/done');
  }

  /// The backend requires `employee_id` and rejects any id other than the
  /// authenticated employee's own.
  @override
  Future<Response<dynamic>> getMySales(int employeeId) {
    return dio.get(
      ApiConstants.saleMySales,
      queryParameters: {'employee_id': employeeId},
    );
  }
}
