import 'package:dio/dio.dart';

abstract class TasksRemoteDataSource {
  Future<Response<dynamic>> getPharmacyTasks();

  Future<Response<dynamic>> createTask(Map<String, dynamic> data);

  Future<Response<dynamic>> deleteTask(int id);
}
