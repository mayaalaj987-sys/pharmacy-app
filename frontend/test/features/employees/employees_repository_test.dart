import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/employees/data/employees_remote_data_source.dart';
import 'package:phamacy_managment/features/employees/data/employees_repository.dart';
import 'package:phamacy_managment/features/employees/presentation/cubit/employees_cubit.dart';
import 'package:phamacy_managment/features/employees/presentation/cubit/employees_state.dart';

void main() {
  test('fetchPharmacyEmployees parses the SafeEmployeeResource shape', () async {
    final employees =
        await EmployeesRepository(FakeEmployeesApi()).fetchPharmacyEmployees(7);

    expect(employees, hasLength(1));
    final e = employees.first;
    expect(e.id, 1);
    expect(e.pharmacyId, 7);
    expect(e.name, 'Existing Employee');
    expect(e.email, 'emp@example.test');
    expect(e.phone, '0999000000');
    expect(e.role, 'employee');
    expect(e.roleLabel, 'Employee');
    expect(e.status, 'approved');
    expect(e.salary, 500.0);
    expect(e.isTrainee, isFalse);
  });

  test('pending employees parse with null pharmacy and null salary', () async {
    final pending =
        await EmployeesRepository(FakeEmployeesApi()).fetchPendingEmployees();

    expect(pending, hasLength(2));
    expect(pending.first.pharmacyId, isNull);
    expect(pending.first.status, 'pending');
    // Trainees carry no salary.
    expect(pending.last.isTrainee, isTrue);
    expect(pending.last.salary, isNull);
    expect(pending.last.roleLabel, 'Trainee');
  });

  test('approve sends salary only when provided', () async {
    final api = FakeEmployeesApi();
    final repo = EmployeesRepository(api);

    await repo.approveEmployee(2, salary: 450);
    expect(api.lastApprovePayload, {'salary': 450.0});

    await repo.approveEmployee(3);
    expect(api.lastApprovePayload, isEmpty);
  });

  test('load populates both current and pending lists', () async {
    final cubit = EmployeesCubit(EmployeesRepository(FakeEmployeesApi()));

    await cubit.load(7);

    expect(cubit.state.status, EmployeesStatus.ready);
    expect(cubit.state.current, hasLength(1));
    expect(cubit.state.pending, hasLength(2));
    expect(cubit.state.atCapacity, isFalse);
    await cubit.close();
  });

  test('the 2-employee cap rejection is surfaced verbatim', () async {
    final api = FakeEmployeesApi()..failApproveWithCap = true;
    final cubit = EmployeesCubit(EmployeesRepository(api));
    await cubit.load(7);

    final ok = await cubit.approve(7, 2, salary: 400);

    expect(ok, isFalse);
    // Surfaced verbatim from the backend (Arabic message naming the "(2)" cap).
    expect(cubit.state.error!.message, contains('(2)'));
    expect(cubit.state.error!.message, isNotEmpty);
    expect(cubit.state.mutatingEmployeeId, isNull);
    // Nothing was added locally.
    expect(cubit.state.current, hasLength(1));
    await cubit.close();
  });

  test('a 409 document-retention refusal keeps the employee in the list', () async {
    final api = FakeEmployeesApi()..failDismissWithRetention = true;
    final cubit = EmployeesCubit(EmployeesRepository(api));
    await cubit.load(7);

    final ok = await cubit.dismiss(7, 1);

    expect(ok, isFalse);
    expect(
      cubit.state.error!.message,
      contains('retention policy'),
    );
    // The employee must NOT be removed locally when the API rejects it.
    expect(cubit.state.current, hasLength(1));
    await cubit.close();
  });

  test('a successful dismiss refetches authoritative state', () async {
    final api = FakeEmployeesApi();
    final cubit = EmployeesCubit(EmployeesRepository(api));
    await cubit.load(7);
    api.dismissed = true;

    final ok = await cubit.dismiss(7, 1);

    expect(ok, isTrue);
    expect(cubit.state.current, isEmpty);
    expect(cubit.state.mutatingEmployeeId, isNull);
    await cubit.close();
  });

  test('atCapacity reflects the backend maximum of two', () async {
    final api = FakeEmployeesApi()..twoEmployees = true;
    final cubit = EmployeesCubit(EmployeesRepository(api));

    await cubit.load(7);

    expect(cubit.state.current, hasLength(2));
    expect(cubit.state.atCapacity, isTrue);
    await cubit.close();
  });
}

class FakeEmployeesApi implements EmployeesRemoteDataSource {
  Map<String, dynamic>? lastApprovePayload;
  bool failApproveWithCap = false;
  bool failDismissWithRetention = false;
  bool dismissed = false;
  bool twoEmployees = false;

  @override
  Future<Response<dynamic>> getPharmacyEmployees(int pharmacyId) async {
    final rows = dismissed
        ? <Map<String, dynamic>>[]
        : [
            {
              'id': 1,
              'pharmacy_id': 7,
              'name': 'Existing Employee',
              'phone': '0999000000',
              'email': 'emp@example.test',
              'role': 'employee',
              'status': 'approved',
              'salary': 500,
              'created_at': '2026-08-01T00:00:00.000Z',
            },
            if (twoEmployees)
              {
                'id': 4,
                'pharmacy_id': 7,
                'name': 'Second Employee',
                'phone': '0999000004',
                'email': 'emp4@example.test',
                'role': 'employee',
                'status': 'approved',
                'salary': 450,
              },
          ];

    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employees/$pharmacyId'),
      data: {'employees': rows},
    );
  }

  @override
  Future<Response<dynamic>> getPendingEmployees() async {
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employees/pending'),
      data: {
        'count': 2,
        'employees': [
          {
            'id': 2,
            'pharmacy_id': null,
            'name': 'Applicant One',
            'phone': '0999000002',
            'email': 'a1@example.test',
            'role': 'employee',
            'status': 'pending',
            'salary': null,
          },
          {
            'id': 3,
            'pharmacy_id': null,
            'name': 'Applicant Two',
            'phone': '0999000003',
            'email': 'a2@example.test',
            'role': 'trainee',
            'status': 'pending',
            'salary': null,
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> approveEmployee(
    int id,
    Map<String, dynamic> data,
  ) async {
    lastApprovePayload = data;
    if (failApproveWithCap) {
      throw DioException(
        requestOptions: RequestOptions(path: '/employees/approve/$id'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/employees/approve/$id'),
          statusCode: 400,
          data: {'message': 'هذه الصيدلية وصلت للحد الأقصى (2)'},
        ),
        type: DioExceptionType.badResponse,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employees/approve/$id'),
      data: {'message': 'تم القبول بنجاح'},
    );
  }

  @override
  Future<Response<dynamic>> dismissEmployee(int id) async {
    if (failDismissWithRetention) {
      throw DioException(
        requestOptions: RequestOptions(path: '/employees/$id/dismiss'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/employees/$id/dismiss'),
          statusCode: 409,
          data: {
            'message':
                'This employee cannot be dismissed until the recruitment-document retention policy is defined.',
            'code': 'employee_document_retention_required',
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    dismissed = true;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/employees/$id/dismiss'),
      data: {'message': 'تم حذف الموظف من النظام بنجاح'},
    );
  }
}
