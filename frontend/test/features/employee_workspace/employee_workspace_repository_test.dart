import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/employee_workspace/data/employee_workspace_remote_data_source.dart';
import 'package:phamacy_managment/features/employee_workspace/data/employee_workspace_repository.dart';
import 'package:phamacy_managment/features/employee_workspace/presentation/cubit/employee_workspace_cubit.dart';
import 'package:phamacy_managment/features/employee_workspace/presentation/cubit/employee_workspace_state.dart';

void main() {
  test('fetchMyTasks parses counts and task rows', () async {
    final result = await EmployeeWorkspaceRepository(
      FakeWorkspaceApi(),
    ).fetchMyTasks();

    expect(result.pendingCount, 1);
    expect(result.doneCount, 1);
    expect(result.tasks, hasLength(2));
    expect(result.tasks.first.title, 'Restock shelf A');
    expect(result.tasks.first.isDone, isFalse);
    expect(result.tasks.first.statusLabel, 'Pending');
    expect(result.tasks.last.isDone, isTrue);
    // Empty descriptions normalise to null.
    expect(result.tasks.last.description, isNull);
  });

  test('fetchMySales sends the employee id required by the backend', () async {
    final api = FakeWorkspaceApi();

    final sales = await EmployeeWorkspaceRepository(api).fetchMySales(9);

    expect(api.lastSalesEmployeeId, 9);
    expect(sales.totalSales, 2);
    expect(sales.totalPrice, 150.0);
    expect(sales.sales, hasLength(1));
    expect(sales.sales.first.id, 5);
  });

  test('load populates tasks and sales together', () async {
    final cubit = EmployeeWorkspaceCubit(
      EmployeeWorkspaceRepository(FakeWorkspaceApi()),
    );

    await cubit.load(9);

    expect(cubit.state.status, EmployeeWorkspaceStatus.ready);
    expect(cubit.state.tasks.tasks, hasLength(2));
    expect(cubit.state.sales.totalSales, 2);
    await cubit.close();
  });

  test(
    'marking a task done refetches tasks and clears the busy flag',
    () async {
      final api = FakeWorkspaceApi();
      final cubit = EmployeeWorkspaceCubit(EmployeeWorkspaceRepository(api));
      await cubit.load(9);

      final ok = await cubit.markTaskDone(9, 1);

      expect(ok, isTrue);
      expect(api.doneTaskIds, [1]);
      expect(cubit.state.mutatingTaskId, isNull);
      await cubit.close();
    },
  );

  test('an already-done rejection is surfaced and changes nothing', () async {
    final api = FakeWorkspaceApi()..failMarkDone = true;
    final cubit = EmployeeWorkspaceCubit(EmployeeWorkspaceRepository(api));
    await cubit.load(9);

    final ok = await cubit.markTaskDone(9, 1);

    expect(ok, isFalse);
    expect(cubit.state.error, isNotNull);
    expect(cubit.state.mutatingTaskId, isNull);
    expect(cubit.state.tasks.tasks, hasLength(2));
    await cubit.close();
  });

  test('a load failure surfaces an error state', () async {
    final cubit = EmployeeWorkspaceCubit(
      EmployeeWorkspaceRepository(FakeWorkspaceApi()..failLoad = true),
    );

    await cubit.load(9);

    expect(cubit.state.status, EmployeeWorkspaceStatus.failure);
    expect(cubit.state.error, isNotNull);
    await cubit.close();
  });

  test('updateProfile only sends the fields the backend accepts', () async {
    final api = FakeWorkspaceApi();

    await EmployeeWorkspaceRepository(
      api,
    ).updateProfile(name: 'Lina Haddad', phone: '0930111222');

    expect(api.lastProfilePayload, {
      'name': 'Lina Haddad',
      'phone': '0930111222',
    });
  });

  test('updateProfile omits an absent phone instead of sending null', () async {
    final api = FakeWorkspaceApi();

    await EmployeeWorkspaceRepository(api).updateProfile(name: 'Lina Haddad');

    expect(api.lastProfilePayload, {'name': 'Lina Haddad'});
    expect(api.lastProfilePayload!.containsKey('phone'), isFalse);
  });

  test(
    'changePassword forwards the confirmation the backend requires',
    () async {
      final api = FakeWorkspaceApi();

      await EmployeeWorkspaceRepository(api).changePassword(
        currentPassword: 'old-secret-1',
        newPassword: 'new-secret-1',
        confirmation: 'new-secret-1',
      );

      expect(api.lastPasswordPayload, {
        'current_password': 'old-secret-1',
        'new_password': 'new-secret-1',
        'new_password_confirmation': 'new-secret-1',
      });
    },
  );

  test('changePassword surfaces the backend rejection code', () async {
    final api = FakeWorkspaceApi()..failPasswordChange = true;
    final cubit = EmployeeWorkspaceCubit(EmployeeWorkspaceRepository(api));

    final ok = await cubit.changePassword(
      currentPassword: 'wrong',
      newPassword: 'new-secret-1',
      confirmation: 'new-secret-1',
    );

    expect(ok, isFalse);
    expect(cubit.state.error?.code, 'current_password_incorrect');
    expect(cubit.state.savingAccount, isFalse);
    await cubit.close();
  });
}

class FakeWorkspaceApi implements EmployeeWorkspaceRemoteDataSource {
  int? lastSalesEmployeeId;
  final List<int> doneTaskIds = [];
  bool failMarkDone = false;
  bool failLoad = false;
  Map<String, dynamic>? lastProfilePayload;
  Map<String, dynamic>? lastPasswordPayload;
  bool failPasswordChange = false;

  @override
  Future<Response<dynamic>> getMyTasks() async {
    if (failLoad) {
      throw DioException(
        requestOptions: RequestOptions(path: '/tasks'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/tasks'),
          statusCode: 500,
          data: {'message': 'Server error'},
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/tasks'),
      data: {
        'pending_count': 1,
        'done_count': 1,
        'tasks': [
          {
            'id': 1,
            'title': 'Restock shelf A',
            'description': 'Refill painkillers',
            'status': 'pending',
            'created_at': '2026-08-16T09:00:00.000Z',
          },
          {
            'id': 2,
            'title': 'Clean counter',
            'description': '',
            'status': 'done',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> markTaskDone(int taskId) async {
    if (failMarkDone) {
      throw DioException(
        requestOptions: RequestOptions(path: '/tasks/$taskId/done'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/tasks/$taskId/done'),
          statusCode: 400,
          data: {'message': 'Task already completed'},
        ),
        type: DioExceptionType.badResponse,
      );
    }
    doneTaskIds.add(taskId);
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/tasks/$taskId/done'),
      data: {'message': 'ok'},
    );
  }

  @override
  Future<Response<dynamic>> getMySales(int employeeId) async {
    lastSalesEmployeeId = employeeId;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/sale/my-sales'),
      data: {
        'total_sales': 2,
        'total_price': 150,
        'sales': [
          {
            'id': 5,
            'customer_name': 'Sam',
            'payment_method': 'cash',
            'total_price': 75,
            'date': '2026-08-16',
            'items': const [],
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> updateProfile(Map<String, dynamic> data) async {
    lastProfilePayload = data;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employee/profile/update'),
      data: {'code': 'employee_profile_updated'},
    );
  }

  @override
  Future<Response<dynamic>> changePassword(Map<String, dynamic> data) async {
    lastPasswordPayload = data;
    if (failPasswordChange) {
      throw DioException(
        requestOptions: RequestOptions(path: '/employee/password/change'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/employee/password/change'),
          statusCode: 422,
          data: {
            'code': 'current_password_incorrect',
            'message': 'Current password is incorrect.',
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employee/password/change'),
      data: {'code': 'password_changed'},
    );
  }
}
