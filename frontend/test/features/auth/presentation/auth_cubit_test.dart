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
    final cubit = AuthCubit(AuthRepository(api, storage, AuthSessionEvents()));

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
    final cubit = AuthCubit(AuthRepository(api, storage, AuthSessionEvents()));

    await cubit.restoreSession();

    expect(cubit.state, isA<AuthUnauthenticated>());
    expect(storage.token, isNull);
    expect(storage.activePharmacyId, isNull);
    await cubit.close();
  });

  test('clears stale active pharmacy and retries restoration once', () async {
    final storage = FakeSessionStorage(token: 'token', activePharmacyId: 99);
    final api = FakeAuthApi()
      ..meResults.add(_sessionResponse(code: 'stale_active_pharmacy'))
      ..meResults.add(_sessionResponse(activeId: 1));
    final repository = AuthRepository(api, storage, AuthSessionEvents());

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
    final cubit = AuthCubit(AuthRepository(api, storage, AuthSessionEvents()));

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
      final repository = AuthRepository(api, storage, AuthSessionEvents());

      await repository.logout();

      expect(api.logoutCalls, 1);
      expect(storage.token, isNull);
      expect(storage.activePharmacyId, isNull);
    },
  );
}

Response<dynamic> _sessionResponse({
  int? activeId,
  String code = 'ready',
  bool requiresActive = false,
  int pharmacyCount = 1,
}) {
  final pharmacies = List.generate(pharmacyCount, (index) {
    final id = index + 1;
    return {
      'id': id,
      'name': 'Pharmacy $id',
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
            'name': 'Owner',
            'email': 'owner@example.test',
            'profile_image': null,
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

class FakeSessionStorage implements SessionStorage {
  String? token;
  int? activePharmacyId;

  FakeSessionStorage({this.token, this.activePharmacyId});

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
  Future<int?> getActivePharmacyId() async => activePharmacyId;

  @override
  Future<String?> getToken() async => token;

  @override
  Future<void> saveActivePharmacyId(int pharmacyId) async {
    activePharmacyId = pharmacyId;
  }

  @override
  Future<void> saveToken(String value) async => token = value;
}

class FakeAuthApi implements AuthRemoteDataSource {
  final List<Object> meResults = [];
  int meCalls = 0;
  int logoutCalls = 0;
  int? lastRequestedPharmacyId;
  bool logoutFails = false;

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
  Future<Response<dynamic>> employeeLogin(String email, String password) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> login(String email, String password) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registerEmployee(FormData data) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registerPharmacist(FormData data) =>
      throw UnimplementedError();
}
