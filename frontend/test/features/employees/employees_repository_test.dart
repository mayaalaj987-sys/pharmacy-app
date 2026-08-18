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
    expect(e.shift, 'morning');
    expect(e.shiftLabel, 'Morning');
    expect(e.salary, 500.0);
    expect(e.isTrainee, isFalse);
  });

  test('pool applicants parse without any way to contact them', () async {
    final pending =
        await EmployeesRepository(FakeEmployeesApi()).fetchPendingEmployees();

    expect(pending, hasLength(2));
    expect(pending.first.name, 'Applicant One');
    expect(pending.first.pharmacyId, isNull);

    // The listing carries no phone and no email at all, so a recruiter cannot
    // scrape contact details off a page of strangers.
    expect(pending.first.hasContactDetails, isFalse);
    expect(pending.first.phone, isEmpty);
    expect(pending.first.email, isEmpty);

    // What a hiring decision actually rests on.
    expect(pending.first.hasCv, isTrue);
    expect(pending.first.hasExperienceProof, isTrue);
    expect(pending.last.hasExperienceProof, isFalse);
    expect(pending.last.roleLabel, 'Trainee');
  });

  test('a pool applicant carries the offer this pharmacy made', () async {
    final pending =
        await EmployeesRepository(FakeEmployeesApi()).fetchPendingEmployees();

    // Which shift was offered, so two outstanding offers are distinguishable.
    // Without it a pharmacist could not tell whom they had offered mornings to.
    expect(pending.first.offerStatus, 'pending');
    expect(pending.first.offerShift, 'morning');
    expect(pending.first.offerShiftLabel, 'Morning');

    expect(pending.last.offerStatus, isNull);
    expect(pending.last.offerShiftLabel, isNull);
  });

  test('applicant documents parse without exposing the file itself', () async {
    final docs = await EmployeesRepository(
      FakeEmployeesApi(),
    ).fetchApplicantDocuments(2);

    expect(docs, hasLength(1));
    expect(docs.first.label, 'CV');
    expect(docs.first.fileExtension, 'pdf');
    expect(docs.first.sizeLabel, '2 KB');
    expect(docs.first.downloadUrl, contains('/documents/'));
  });

  test('an offer names the applicant and the shift', () async {
    final api = FakeEmployeesApi();
    final repo = EmployeesRepository(api);

    await repo.sendOffer(2, shift: 'evening', salary: 450);
    expect(api.lastOfferPayload, {
      'employee_id': 2,
      'shift': 'evening',
      'salary': 450.0,
    });

    // Salary is optional and never withheld by role: a trainee may be paid.
    await repo.sendOffer(3, shift: 'morning');
    expect(api.lastOfferPayload, {'employee_id': 3, 'shift': 'morning'});
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

  test('a covered shift is refused and nothing changes locally', () async {
    final api = FakeEmployeesApi()..failApproveWithCap = true;
    final cubit = EmployeesCubit(EmployeesRepository(api));
    await cubit.load(7);

    final ok = await cubit.sendOffer(7, 2, shift: 'morning', salary: 400);

    expect(ok, isFalse);
    // The code is what the client maps; the message is a fallback.
    expect(cubit.state.error!.code, 'shift_taken');
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

  test('capacity is the shift list, not a headcount', () async {
    final cubit = EmployeesCubit(EmployeesRepository(FakeEmployeesApi()));
    await cubit.load(7);

    // One morning person hired: only the evening shift is offerable.
    expect(cubit.state.freeShifts, ['evening']);
    expect(cubit.state.atCapacity, isFalse);
    await cubit.close();

    final full = EmployeesCubit(
      EmployeesRepository(FakeEmployeesApi()..twoEmployees = true),
    );
    await full.load(7);

    expect(full.state.freeShifts, isEmpty);
    expect(full.state.atCapacity, isTrue);
    await full.close();
  });
}

class FakeEmployeesApi implements EmployeesRemoteDataSource {
  Map<String, dynamic>? lastOfferPayload;
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
              'shift': 'morning',
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
                'shift': 'evening',
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
            'name': 'Applicant One',
            'role': 'employee',
            'applied_at': '2026-08-10T00:00:00.000Z',
            'has_cv': true,
            'has_experience_proof': true,
            'offer': {
              'id': 9,
              'status': 'pending',
              'shift': 'morning',
              'salary': 400000,
            },
          },
          {
            'id': 3,
            'name': 'Applicant Two',
            'role': 'trainee',
            'applied_at': '2026-08-11T00:00:00.000Z',
            'has_cv': true,
            'has_experience_proof': false,
            'offer': null,
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> sendOffer(Map<String, dynamic> data) async {
    lastOfferPayload = data;
    final options = RequestOptions(path: '/recruitment/offers');

    if (failApproveWithCap) {
      return Future<Response<dynamic>>.error(
        DioException(
          requestOptions: options,
          response: Response<dynamic>(
            requestOptions: options,
            statusCode: 409,
            data: {
              'message': 'The morning shift is already covered.',
              'code': 'shift_taken',
            },
          ),
          type: DioExceptionType.badResponse,
        ),
      );
    }

    return Response<dynamic>(
      requestOptions: options,
      data: {'message': 'Offer sent.', 'code': 'offer_sent'},
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

  @override
  Future<Response<dynamic>> getApplicantDocuments(int employeeId) async {
    return Response<dynamic>(
      requestOptions: RequestOptions(
        path: '/recruitment/applicants/$employeeId/documents',
      ),
      data: {
        'data': [
          {
            'id': 'a1b2c3',
            'type': 'cv',
            'version': 1,
            'mime_type': 'application/pdf',
            'size_bytes': 2048,
            'uploaded_at': '2026-08-10T00:00:00.000Z',
            'preview_url':
                'http://x.test/api/recruitment/applicants/2/documents/a1b2c3/preview',
            'download_url':
                'http://x.test/api/recruitment/applicants/2/documents/a1b2c3/download',
          },
        ],
        'applicant': {'id': 2, 'name': 'Applicant One', 'role': 'employee'},
      },
    );
  }

  @override
  Future<Response<List<int>>> downloadDocument(String url) async {
    return Response<List<int>>(
      requestOptions: RequestOptions(path: url),
      data: const <int>[1, 2, 3],
    );
  }
}
