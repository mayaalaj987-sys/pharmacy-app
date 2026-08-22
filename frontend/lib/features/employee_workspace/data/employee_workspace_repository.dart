import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../../sales/domain/sale.dart';
import '../domain/employee_task.dart';
import 'employee_workspace_remote_data_source.dart';

class MyTasks {
  final int pendingCount;
  final int doneCount;
  final List<EmployeeTask> tasks;

  const MyTasks({
    required this.pendingCount,
    required this.doneCount,
    required this.tasks,
  });

  static const empty = MyTasks(pendingCount: 0, doneCount: 0, tasks: []);
}

class MySales {
  final int totalSales;
  final double totalPrice;
  final List<Sale> sales;

  const MySales({
    required this.totalSales,
    required this.totalPrice,
    required this.sales,
  });

  static const empty = MySales(totalSales: 0, totalPrice: 0, sales: []);
}

class EmployeeWorkspaceRepository {
  final EmployeeWorkspaceRemoteDataSource api;

  const EmployeeWorkspaceRepository(this.api);

  Future<MyTasks> fetchMyTasks() async {
    try {
      final response = await api.getMyTasks();
      final data = response.data;
      if (data is! Map<String, dynamic>) return MyTasks.empty;
      final raw = data['tasks'];

      return MyTasks(
        pendingCount: _toInt(data['pending_count']),
        doneCount: _toInt(data['done_count']),
        tasks: raw is List
            ? raw
                  .whereType<Map<String, dynamic>>()
                  .map(EmployeeTask.fromJson)
                  .toList(growable: false)
            : const <EmployeeTask>[],
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> markTaskDone(int taskId) async {
    try {
      await api.markTaskDone(taskId);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<MySales> fetchMySales(int employeeId) async {
    try {
      final response = await api.getMySales(employeeId);
      final data = response.data;
      if (data is! Map<String, dynamic>) return MySales.empty;
      final raw = data['sales'];

      return MySales(
        totalSales: _toInt(data['total_sales']),
        totalPrice: _toDouble(data['total_price']),
        sales: raw is List
            ? raw
                  .whereType<Map<String, dynamic>>()
                  .map(Sale.fromJson)
                  .toList(growable: false)
            : const <Sale>[],
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Only name and phone are accepted by the backend; every other field is
  /// rejected server-side as a prohibited attribute.
  ///
  /// A blank phone is omitted rather than sent. Laravel converts an empty
  /// string to null before validation, so posting one would erase a stored
  /// number — an edit nobody asked for. Clearing a phone is not a thing this
  /// screen offers, so "empty" can only mean "left alone".
  Future<void> updateProfile({required String name, String? phone}) async {
    final trimmed = phone?.trim();
    try {
      await api.updateProfile({
        'name': name,
        if (trimmed != null && trimmed.isNotEmpty) 'phone': trimmed,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    try {
      await api.changePassword({
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': confirmation,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  static int _toInt(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;
}
