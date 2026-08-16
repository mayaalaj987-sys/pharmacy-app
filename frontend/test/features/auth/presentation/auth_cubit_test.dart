import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/network/auth_session_events.dart';
import 'package:phamacy_managment/core/storage/secure_storage_service.dart';
import 'package:phamacy_managment/features/auth/data/datasource/auth_remote_datasource.dart';
import 'package:phamacy_managment/features/auth/data/datasource/auth_repository.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_state.dart';

void main() {
  test('restores a valid authenticated session', () async {
    final storage = FakeSessionStorage(token: 'token');
    final api = FakeAuthApi()..meResults.add(_sessionResponse(activeId: 1));
    final cubit = AuthCubit(
      AuthRepository(api, storage, storage, AuthSessionEvents()),
    );

    await cubit.restoreSession();

    expect(cubit.state, isA<AuthAuthenticated>());
    expect(storage.activePharmacyId, 1);
    await cubit.close();
  });

  test('clears an invalid restored session after 401', () async {
    final storage = FakeSessionStorage(token: 'expired');
    final api = FakeAuthApi()
      ..meResults.add(
        DioException(
          requestOptions: RequestOptions(path: '/me'),
          response: Response<dynamic>(
            requestOptions: RequestOptions(path: '/me'),
            statusCode: 401,
            data: {'message': 'Unauthenticated.', 'code': 'unauthenticated'},
          ),
          type: DioExceptionType.badResponse,
        ),
      );
    final cubit = AuthCubit(
      AuthRepository(api, storage, storage, AuthSessionEvents()),
    );

    await cubit.restoreSession();

    expect(cubit.state, isA<AuthUnauthenticated>());
    expect(storage.token, isNull);
    expect(storage.activePharmacyId, isNull);
    await cubit.close();
  });

  test(
    'restored me session keeps updated profile and pharmacy values',
    () async {
      final storage = FakeSessionStorage(token: 'token', activePharmacyId: 1);
      final api = FakeAuthApi()
        ..meResults.add(
          _sessionResponse(
            activeId: 1,
            actorName: 'Updated Owner',
            phone: '0999000000',
            profileImageUrl: 'https://api.test/storage/profiles/updated.png',
            pharmacyName: 'Updated Pharmacy',
          ),
        );
      final cubit = AuthCubit(
        AuthRepository(api, storage, storage, AuthSessionEvents()),
      );

      await cubit.restoreSession();

      expect(cubit.session?.actor.name, 'Updated Owner');
      expect(cubit.session?.actor.phone, '0999000000');
      expect(
        cubit.session?.actor.profileImageUrl,
        'https://api.test/storage/profiles/updated.png',
      );
      expect(cubit.session?.activePharmacy?.name, 'Updated Pharmacy');
      await cubit.close();
    },
  );

  test('clears stale active pharmacy and retries restoration once', () async {
    final storage = FakeSessionStorage(token: 'token', activePharmacyId: 99);
    final api = FakeAuthApi()
      ..meResults.add(_sessionResponse(code: 'stale_active_pharmacy'))
      ..meResults.add(_sessionResponse(activeId: 1));
    final repository = AuthRepository(
      api,
      storage,
      storage,
      AuthSessionEvents(),
    );

    final session = await repository.restoreSession();

    expect(session?.activePharmacy?.id, 1);
    expect(api.meCalls, 2);
    expect(storage.activePharmacyId, 1);
  });

  test('selects and persists an active pharmacy', () async {
    final storage = FakeSessionStorage(token: 'token');
    final api = FakeAuthApi()
      ..meResults.add(
        _sessionResponse(
          code: 'active_pharmacy_required',
          requiresActive: true,
          pharmacyCount: 2,
        ),
      )
      ..meResults.add(_sessionResponse(activeId: 2, pharmacyCount: 2));
    final cubit = AuthCubit(
      AuthRepository(api, storage, storage, AuthSessionEvents()),
    );

    await cubit.restoreSession();
    expect(cubit.state, isA<AuthPharmacySelectionRequired>());

    await cubit.selectActivePharmacy(2);

    expect(cubit.state, isA<AuthAuthenticated>());
    expect(storage.activePharmacyId, 2);
    expect(api.lastRequestedPharmacyId, 2);
    await cubit.close();
  });

  test(
    'logout clears local session even when the server is unavailable',
    () async {
      final storage = FakeSessionStorage(token: 'token', activePharmacyId: 1);
      final api = FakeAuthApi()..logoutFails = true;
      final repository = AuthRepository(
        api,
        storage,
        storage,
        AuthSessionEvents(),
      );

      await repository.logout();

      expect(api.logoutCalls, 1);
      expect(storage.token, isNull);
      expect(storage.activePharmacyId, isNull);
    },
  );

  test(
    'pharmacist registration stores only the status token and stays separate from authenticated state',
    () async {
      final storage = FakeSessionStorage();
      final api = FakeAuthApi()
        ..registerPharmacistResult = _registrationResponse();
      final cubit = AuthCubit(
        AuthRepository(api, storage, storage, AuthSessionEvents()),
      );

      await cubit.registerPharmacist(FormData());

      expect(cubit.state, isA<PharmacistRegistrationStatus>());
      expect(cubit.state, isNot(isA<AuthAuthenticated>()));
      expect(storage.registrationStatusToken, 'status-token');
      expect(storage.token, isNull);
      await cubit.close();
    },
  );

  test('approval refresh never emits AuthAuthenticated', () async {
    final storage = FakeSessionStorage(registrationStatusToken: 'status-token');
    final api = FakeAuthApi()
      ..registerPharmacistResult = _registrationResponse()
      ..registrationStatusResults.add(
        _registrationStatusResponse(status: 'approved'),
      );
    final cubit = AuthCubit(
      AuthRepository(api, storage, storage, AuthSessionEvents()),
    );
    final emitted = <AuthState>[];
    final subscription = cubit.stream.listen(emitted.add);

    await cubit.registerPharmacist(FormData());
    await cubit.refreshRegistrationStatus();

    expect(
      (cubit.state as PharmacistRegistrationStatus).registration.isApproved,
      isTrue,
    );
    expect(emitted.whereType<AuthAuthenticated>(), isEmpty);
    expect(storage.token, isNull);
    await subscription.cancel();
    await cubit.close();
  });

  test(
    'going to login clears only the status token even when revocation is unreachable',
    () async {
      final storage = FakeSessionStorage(
        token: 'unrelated-app-token',
        activePharmacyId: 7,
        registrationStatusToken: 'status-token',
      );
      final api = FakeAuthApi()..statusLogoutFails = true;
      final repository = AuthRepository(
        api,
        storage,
        storage,
        AuthSessionEvents(),
      );

      await repository.finishRegistrationStatus();

      expect(api.statusLogoutToken, 'status-token');
      expect(storage.registrationStatusToken, isNull);
      expect(storage.token, 'unrelated-app-token');
      expect(storage.activePharmacyId, 7);
    },
  );

  test(
    'pending pharmacist login remains an error instead of a status session',
    () async {
      final storage = FakeSessionStorage(
        registrationStatusToken: 'unrelated-status-token',
      );
      final api = FakeAuthApi()
        ..loginError = DioException(
          requestOptions: RequestOptions(path: '/login'),
          response: Response<dynamic>(
            requestOptions: RequestOptions(path: '/login'),
            statusCode: 403,
            data: {
              'message': 'Your pharmacy registration is awaiting approval.',
              'code': 'pharmacy_review_required',
            },
          ),
          type: DioExceptionType.badResponse,
        );
      final cubit = AuthCubit(
        AuthRepository(api, storage, storage, AuthSessionEvents()),
      );

      await cubit.login('pending@example.test', 'password');

      expect(cubit.state, isA<AuthError>());
      expect(cubit.state, isNot(isA<AuthAccessRestricted>()));
      expect((cubit.state as AuthError).code, 'pharmacy_review_required');
      expect(storage.token, isNull);
      expect(storage.registrationStatusToken, 'unrelated-status-token');
      await cubit.close();
    },
  );
}

Response<dynamic> _sessionResponse({
  int? activeId,
  String code = 'ready',
  bool requiresActive = false,
  int pharmacyCount = 1,
  String actorName = 'Owner',
  String? phone,
  String? profileImageUrl,
  String pharmacyName = 'Pharmacy',
}) {
  final pharmacies = List.generate(pharmacyCount, (index) {
    final id = index + 1;
    return {
      'id': id,
      'name': pharmacyCount == 1 ? pharmacyName : '$pharmacyName $id',
      'address': 'Address $id',
      'status': 'approved',
    };
  });

  return Response<dynamic>(
    requestOptions: RequestOptions(path: '/me'),
    statusCode: 200,
    data: {
      'data': {
        'session': {
          'actor': {
            'id': 1,
            'type': 'pharmacist',
            'role': 'owner',
            'status': null,
            'name': actorName,
            'email': 'owner@example.test',
            'phone': phone,
            'profile_image_url': profileImageUrl,
          },
          'available_pharmacies': pharmacies,
          'active_pharmacy': activeId == null
              ? null
              : pharmacies.firstWhere((item) => item['id'] == activeId),
          'access': {
            'operational': activeId != null,
            'code': code,
            'requires_active_pharmacy': requiresActive,
          },
        },
      },
    },
  );
}

Response<dynamic> _registrationResponse() {
  return Response<dynamic>(
    requestOptions: RequestOptions(path: '/register'),
    statusCode: 201,
    data: {
      'data': {
        'registration_status_token': 'status-token',
        'registration': _registrationJson(),
      },
    },
  );
}

Response<dynamic> _registrationStatusResponse({required String status}) {
  return Response<dynamic>(
    requestOptions: RequestOptions(path: '/registration/status'),
    statusCode: 200,
    data: {
      'data': {'registration': _registrationJson(status: status)},
    },
  );
}

Map<String, dynamic> _registrationJson({String status = 'pending'}) {
  final approved = status == 'approved';
  return {
    'status': status,
    'code': approved ? 'pharmacy_approved' : 'pharmacy_review_required',
    'message': approved
        ? 'Your pharmacy has been approved. You can now log in.'
        : 'Your pharmacy registration is awaiting approval.',
    'pharmacies': [
      {'id': 1, 'name': 'Pharmacy 1', 'address': 'Address 1', 'status': status},
    ],
  };
}

class FakeSessionStorage implements SessionStorage, RegistrationStatusStorage {
  String? token;
  int? activePharmacyId;
  String? registrationStatusToken;

  FakeSessionStorage({
    this.token,
    this.activePharmacyId,
    this.registrationStatusToken,
  });

  @override
  Future<void> clearActivePharmacy() async => activePharmacyId = null;

  @override
  Future<void> clearSession() async {
    token = null;
    activePharmacyId = null;
  }

  @override
  Future<void> clearToken() async => token = null;

  @override
  Future<void> clearRegistrationStatusToken() async {
    registrationStatusToken = null;
  }

  @override
  Future<int?> getActivePharmacyId() async => activePharmacyId;

  @override
  Future<String?> getToken() async => token;

  @override
  Future<String?> getRegistrationStatusToken() async => registrationStatusToken;

  @override
  Future<void> saveActivePharmacyId(int pharmacyId) async {
    activePharmacyId = pharmacyId;
  }

  @override
  Future<void> saveToken(String value) async => token = value;

  @override
  Future<void> saveRegistrationStatusToken(String value) async {
    registrationStatusToken = value;
  }
}

class FakeAuthApi implements AuthRemoteDataSource {
  final List<Object> meResults = [];
  int meCalls = 0;
  int logoutCalls = 0;
  int? lastRequestedPharmacyId;
  bool logoutFails = false;
  bool statusLogoutFails = false;
  String? statusLogoutToken;
  Response<dynamic>? registerPharmacistResult;
  DioException? loginError;
  final List<Object> registrationStatusResults = [];

  @override
  Future<Response<dynamic>> me({int? activePharmacyId}) async {
    meCalls++;
    lastRequestedPharmacyId = activePharmacyId;
    final result = meResults.removeAt(0);
    if (result is DioException) throw result;
    return result as Response<dynamic>;
  }

  @override
  Future<Response<dynamic>> logout() async {
    logoutCalls++;
    if (logoutFails) {
      throw DioException(
        requestOptions: RequestOptions(path: '/logout'),
        type: DioExceptionType.connectionError,
      );
    }
    return Response<dynamic>(requestOptions: RequestOptions(path: '/logout'));
  }

  @override
  Future<Response<dynamic>> logoutRegistrationStatus(String statusToken) async {
    statusLogoutToken = statusToken;
    if (statusLogoutFails) {
      throw DioException(
        requestOptions: RequestOptions(path: '/logout'),
        type: DioExceptionType.connectionError,
      );
    }
    return Response<dynamic>(requestOptions: RequestOptions(path: '/logout'));
  }

  @override
  Future<Response<dynamic>> registrationStatus(String statusToken) async {
    final result = registrationStatusResults.removeAt(0);
    if (result is DioException) throw result;
    return result as Response<dynamic>;
  }

  @override
  Future<Response<dynamic>> employeeLogin(String email, String password) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> login(String email, String password) async {
    if (loginError != null) throw loginError!;
    throw UnimplementedError();
  }

  @override
  Future<Response<dynamic>> registerEmployee(FormData data) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registerPharmacist(FormData data) async =>
      registerPharmacistResult ?? (throw UnimplementedError());
}
