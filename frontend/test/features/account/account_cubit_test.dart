import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/network/auth_session_events.dart';
import 'package:phamacy_managment/core/storage/secure_storage_service.dart';
import 'package:phamacy_managment/features/account/data/account_remote_data_source.dart';
import 'package:phamacy_managment/features/account/data/account_repository.dart';
import 'package:phamacy_managment/features/account/presentation/cubit/account_cubit.dart';
import 'package:phamacy_managment/features/account/presentation/cubit/account_state.dart';
import 'package:phamacy_managment/features/auth/data/datasource/auth_remote_datasource.dart';
import 'package:phamacy_managment/features/auth/data/datasource/auth_repository.dart';
import 'package:phamacy_managment/features/auth/data/models/auth_session_model.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:phamacy_managment/features/auth/presentation/cubit/auth_state.dart';

void main() {
  test(
    'profile update uses focused loading and synchronizes AuthCubit',
    () async {
      final harness = Harness();
      final states = <AccountState>[];
      final subscription = harness.account.stream.listen(states.add);

      final success = await harness.account.updateProfile(
        name: 'Updated Owner',
        phone: '0999000000',
      );

      expect(success, isTrue);
      expect(states.first.loading, isTrue);
      expect(states.first.operation, AccountOperation.profile);
      expect(harness.auth.state, isA<AuthAuthenticated>());
      expect(harness.auth.session?.actor.name, 'Updated Owner');
      expect(harness.account.state.successCode, 'profile_updated');
      await subscription.cancel();
      await harness.close();
    },
  );

  test('password error remains focused and machine-readable', () async {
    final api = FakeAccountCubitApi()..wrongPassword = true;
    final harness = Harness(accountApi: api);

    final success = await harness.account.changePassword(
      currentPassword: 'wrong',
      newPassword: 'new-password',
      confirmation: 'new-password',
    );

    expect(success, isFalse);
    expect(harness.account.state.operation, AccountOperation.password);
    expect(harness.account.state.error?.code, 'current_password_incorrect');
    expect(harness.auth.state, isA<AuthAuthenticated>());
    await harness.close();
  });

  test('duplicate password submissions invoke the API only once', () async {
    final api = FakeAccountCubitApi()..passwordCompleter = Completer<void>();
    final harness = Harness(accountApi: api);

    final first = harness.account.changePassword(
      currentPassword: 'password',
      newPassword: 'new-password',
      confirmation: 'new-password',
    );
    final second = harness.account.changePassword(
      currentPassword: 'password',
      newPassword: 'new-password',
      confirmation: 'new-password',
    );
    await Future<void>.delayed(Duration.zero);

    expect(api.passwordCalls, 1);
    expect(await second, isFalse);
    api.passwordCompleter!.complete();
    expect(await first, isTrue);
    await harness.close();
  });

  test(
    'deactivation clears all credentials and returns auth to login state',
    () async {
      final storage = FakeCubitStorage(
        token: 'token',
        activePharmacyId: 7,
        registrationStatusToken: 'status-token',
      );
      final harness = Harness(storage: storage);

      final success = await harness.account.deactivate('password');

      expect(success, isTrue);
      expect(storage.token, isNull);
      expect(storage.activePharmacyId, isNull);
      expect(storage.registrationStatusToken, isNull);
      expect(harness.auth.state, isA<AuthUnauthenticated>());
      await harness.close();
    },
  );
}

class Harness {
  late final FakeCubitStorage storage;
  late final AuthCubit auth;
  late final AccountCubit account;

  Harness({FakeAccountCubitApi? accountApi, FakeCubitStorage? storage}) {
    this.storage =
        storage ?? FakeCubitStorage(token: 'token', activePharmacyId: 7);
    auth = AuthCubit(
      AuthRepository(
        EmptyAuthApi(),
        this.storage,
        this.storage,
        AuthSessionEvents(),
      ),
    )..synchronizeSession(_session());
    account = AccountCubit(
      AccountRepository(
        accountApi ?? FakeAccountCubitApi(),
        this.storage,
        this.storage,
      ),
      auth,
    );
  }

  Future<void> close() async {
    await account.close();
    await auth.close();
  }
}

class FakeAccountCubitApi implements AccountRemoteDataSource {
  bool wrongPassword = false;
  int passwordCalls = 0;
  Completer<void>? passwordCompleter;

  @override
  Future<Response<dynamic>> updateProfile(FormData data) async {
    final fields = {for (final field in data.fields) field.key: field.value};
    return _sessionResponse(
      name: fields['name'] ?? 'Owner',
      phone: fields['phone'],
    );
  }

  @override
  Future<Response<dynamic>> updatePharmacy(Map<String, dynamic> data) async {
    return _sessionResponse(
      pharmacyName: data['pharmacy_name']?.toString() ?? 'Pharmacy',
    );
  }

  @override
  Future<Response<dynamic>> changePassword(Map<String, dynamic> data) async {
    passwordCalls++;
    if (wrongPassword) {
      throw DioException(
        requestOptions: RequestOptions(path: '/password/change'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/password/change'),
          statusCode: 422,
          data: {
            'message': 'The current password is incorrect.',
            'code': 'current_password_incorrect',
            'errors': {
              'current_password': ['The current password is incorrect.'],
            },
          },
        ),
        type: DioExceptionType.badResponse,
      );
    }
    await passwordCompleter?.future;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/password/change'),
      data: {'code': 'password_changed'},
    );
  }

  @override
  Future<Response<dynamic>> deactivateAccount(Map<String, dynamic> data) async {
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/account/deactivate'),
      data: {'code': 'account_deactivated'},
    );
  }
}

AuthSession _session({
  String name = 'Owner',
  String? phone,
  String pharmacyName = 'Pharmacy',
}) {
  return AuthSession.fromJson(
    _sessionJson(name: name, phone: phone, pharmacyName: pharmacyName),
  );
}

Response<dynamic> _sessionResponse({
  String name = 'Owner',
  String? phone,
  String pharmacyName = 'Pharmacy',
}) {
  return Response<dynamic>(
    requestOptions: RequestOptions(path: '/profile/update'),
    data: {
      'data': {
        'session': _sessionJson(
          name: name,
          phone: phone,
          pharmacyName: pharmacyName,
        ),
      },
    },
  );
}

Map<String, dynamic> _sessionJson({
  required String name,
  String? phone,
  required String pharmacyName,
}) {
  final pharmacy = {
    'id': 7,
    'name': pharmacyName,
    'address': 'Address',
    'status': 'approved',
    'latitude': 33.5,
    'longitude': 36.2,
  };
  return {
    'actor': {
      'id': 1,
      'type': 'pharmacist',
      'role': 'owner',
      'status': null,
      'name': name,
      'email': 'owner@example.test',
      'phone': phone,
      'profile_image_url': null,
    },
    'available_pharmacies': [pharmacy],
    'active_pharmacy': pharmacy,
    'access': {
      'operational': true,
      'code': 'ready',
      'requires_active_pharmacy': false,
    },
  };
}

class EmptyAuthApi implements AuthRemoteDataSource {
  @override
  Future<Response<dynamic>> employeeLogin(String email, String password) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> login(String email, String password) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> logout() => throw UnimplementedError();

  @override
  Future<Response<dynamic>> logoutRegistrationStatus(String statusToken) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> me({int? activePharmacyId}) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registerEmployee(FormData data) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registerPharmacist(FormData data) =>
      throw UnimplementedError();

  @override
  Future<Response<dynamic>> registrationStatus(String statusToken) =>
      throw UnimplementedError();
}

class FakeCubitStorage implements SessionStorage, RegistrationStatusStorage {
  String? token;
  int? activePharmacyId;
  String? registrationStatusToken;

  FakeCubitStorage({
    this.token,
    this.activePharmacyId,
    this.registrationStatusToken,
  });

  @override
  Future<void> clearActivePharmacy() async => activePharmacyId = null;

  @override
  Future<void> clearRegistrationStatusToken() async {
    registrationStatusToken = null;
  }

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
  Future<String?> getRegistrationStatusToken() async => registrationStatusToken;

  @override
  Future<String?> getToken() async => token;

  @override
  Future<void> saveActivePharmacyId(int pharmacyId) async {
    activePharmacyId = pharmacyId;
  }

  @override
  Future<void> saveRegistrationStatusToken(String value) async {
    registrationStatusToken = value;
  }

  @override
  Future<void> saveToken(String value) async => token = value;
}
