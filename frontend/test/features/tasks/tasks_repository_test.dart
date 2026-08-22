import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/tasks/data/tasks_remote_data_source.dart';
import 'package:phamacy_managment/features/tasks/data/tasks_repository.dart';
import 'package:phamacy_managment/features/tasks/presentation/cubit/tasks_cubit.dart';
import 'package:phamacy_managment/features/tasks/presentation/cubit/tasks_state.dart';

void main() {
  test('fetchPharmacyTasks parses counts, employee and description', () async {
    final result = await TasksRepository(FakeTasksApi()).fetchPharmacyTasks();

    expect(result.pendingCount, 1);
    expect(result.doneCount, 1);
    expect(result.tasks, hasLength(2));

    final pending = result.tasks.first;
    expect(pending.id, 1);
    expect(pending.title, 'Restock shelf A');
    expect(pending.description, 'Refill painkillers');
    expect(pending.employeeName, 'Ryan Employee');
    expect(pending.employeeId, 9);
    expect(pending.isDone, isFalse);
    expect(pending.statusLabel, 'Pending');

    final done = result.tasks.last;
    expect(done.isDone, isTrue);
    // Empty description normalises to null; missing employee is labelled.
    expect(done.description, isNull);
    expect(done.employeeName, 'Unassigned');
  });

  test('createTask sends the exact backend contract', () async {
    final api = FakeTasksApi();

    await TasksRepository(api).createTask(
      employeeId: 9,
      title: 'Count inventory',
      description: '  Aisle 3  ',
    );

    expect(api.lastCreatePayload, {
      'employee_id': 9,
      'title': 'Count inventory',
      'description': 'Aisle 3',
    });
  });

  test('createTask omits a blank description', () async {
    final api = FakeTasksApi();

    await TasksRepository(
      api,
    ).createTask(employeeId: 9, title: 'Count inventory', description: '   ');

    expect(api.lastCreatePayload!.containsKey('description'), isFalse);
  });

  test('load exposes ready state with counts', () async {
    final cubit = TasksCubit(TasksRepository(FakeTasksApi()));

    await cubit.load();

    expect(cubit.state.status, TasksStatus.ready);
    expect(cubit.state.tasks.tasks, hasLength(2));
    expect(cubit.state.tasks.pendingCount, 1);
    await cubit.close();
  });

  test('creating a task refetches authoritative state', () async {
    final api = FakeTasksApi();
    final cubit = TasksCubit(TasksRepository(api));

    final ok = await cubit.createTask(employeeId: 9, title: 'New task');

    expect(ok, isTrue);
    expect(api.listCalls, 1);
    expect(cubit.state.creating, isFalse);
    await cubit.close();
  });

  test('a rejected create surfaces the error and stays consistent', () async {
    final api = FakeTasksApi()..failCreate = true;
    final cubit = TasksCubit(TasksRepository(api));
    await cubit.load();

    final ok = await cubit.createTask(employeeId: 99, title: 'Bad assignee');

    expect(ok, isFalse);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.creating, isFalse);
    expect(cubit.state.tasks.tasks, hasLength(2));
    await cubit.close();
  });

  test('deleting a task refetches and clears the busy flag', () async {
    final api = FakeTasksApi();
    final cubit = TasksCubit(TasksRepository(api));
    await cubit.load();

    final ok = await cubit.deleteTask(1);

    expect(ok, isTrue);
    expect(api.deletedIds, [1]);
    expect(cubit.state.deletingTaskId, isNull);
    await cubit.close();
  });

  test('a rejected delete keeps the task list unchanged', () async {
    final api = FakeTasksApi()..failDelete = true;
    final cubit = TasksCubit(TasksRepository(api));
    await cubit.load();

    final ok = await cubit.deleteTask(1);

    expect(ok, isFalse);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.deletingTaskId, isNull);
    expect(cubit.state.tasks.tasks, hasLength(2));
    await cubit.close();
  });
}

class FakeTasksApi implements TasksRemoteDataSource {
  Map<String, dynamic>? lastCreatePayload;
  final List<int> deletedIds = [];
  bool failCreate = false;
  bool failDelete = false;
  int listCalls = 0;

  DioException _error(String path, int status, String message) => DioException(
    requestOptions: RequestOptions(path: path),
    response: Response<dynamic>(
      requestOptions: RequestOptions(path: path),
      statusCode: status,
      data: {'message': message},
    ),
    type: DioExceptionType.badResponse,
  );

  @override
  Future<Response<dynamic>> getPharmacyTasks() async {
    listCalls++;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/tasks/pharmacy'),
      data: {
        'pending_count': 1,
        'done_count': 1,
        'tasks': [
          {
            'id': 1,
            'employee_id': 9,
            'title': 'Restock shelf A',
            'description': 'Refill painkillers',
            'status': 'pending',
            'created_at': '2026-08-16T09:00:00.000Z',
            'employee': {'id': 9, 'name': 'Ryan Employee', 'role': 'employee'},
          },
          {
            'id': 2,
            'employee_id': 9,
            'title': 'Clean counter',
            'description': '',
            'status': 'done',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> createTask(Map<String, dynamic> data) async {
    lastCreatePayload = data;
    if (failCreate) {
      // Mirrors the backend 404 when the employee is not in this pharmacy.
      throw _error('/tasks', 404, 'Employee not found in this pharmacy');
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/tasks'),
      statusCode: 201,
      data: {
        'message': 'created',
        'task': {'id': 3, 'title': data['title'], 'status': 'pending'},
      },
    );
  }

  @override
  Future<Response<dynamic>> deleteTask(int id) async {
    if (failDelete) {
      throw _error('/tasks/$id', 403, 'Forbidden');
    }
    deletedIds.add(id);
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/tasks/$id'),
      data: {'message': 'deleted'},
    );
  }
}
