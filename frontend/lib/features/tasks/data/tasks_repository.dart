import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/pharmacy_task.dart';
import 'tasks_remote_data_source.dart';

class PharmacyTasks {
  final int pendingCount;
  final int doneCount;
  final List<PharmacyTask> tasks;

  const PharmacyTasks({
    required this.pendingCount,
    required this.doneCount,
    required this.tasks,
  });

  static const empty = PharmacyTasks(pendingCount: 0, doneCount: 0, tasks: []);
}

class TasksRepository {
  final TasksRemoteDataSource api;

  const TasksRepository(this.api);

  Future<PharmacyTasks> fetchPharmacyTasks() async {
    try {
      final response = await api.getPharmacyTasks();
      final data = response.data;
      if (data is! Map<String, dynamic>) return PharmacyTasks.empty;
      final raw = data['tasks'];

      int toInt(dynamic v) =>
          v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

      return PharmacyTasks(
        pendingCount: toInt(data['pending_count']),
        doneCount: toInt(data['done_count']),
        tasks: raw is List
            ? raw
                .whereType<Map<String, dynamic>>()
                .map(PharmacyTask.fromJson)
                .toList(growable: false)
            : const <PharmacyTask>[],
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// The backend requires an approved employee of the active pharmacy and a
  /// title of at most 255 characters; description is optional.
  Future<void> createTask({
    required int employeeId,
    required String title,
    String? description,
  }) async {
    try {
      await api.createTask({
        'employee_id': employeeId,
        'title': title,
        if (description != null && description.trim().isNotEmpty)
          'description': description.trim(),
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> deleteTask(int id) async {
    try {
      await api.deleteTask(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
