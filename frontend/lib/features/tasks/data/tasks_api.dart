import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'tasks_remote_data_source.dart';

class TasksApi implements TasksRemoteDataSource {
  final Dio dio;

  TasksApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getPharmacyTasks() {
    return dio.get(ApiConstants.tasksPharmacy);
  }

  @override
  Future<Response<dynamic>> createTask(Map<String, dynamic> data) {
    return dio.post(ApiConstants.tasks, data: data);
  }

  @override
  Future<Response<dynamic>> deleteTask(int id) {
    return dio.delete('${ApiConstants.tasks}/$id');
  }
}
