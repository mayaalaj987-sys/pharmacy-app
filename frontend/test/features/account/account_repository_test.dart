import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/storage/secure_storage_service.dart';
import 'package:phamacy_managment/features/account/data/account_remote_data_source.dart';
import 'package:phamacy_managment/features/account/data/account_repository.dart';
import 'package:phamacy_managment/features/auth/data/models/auth_api_exception.dart';

void main() {
  test(
    'profile update parses refreshed session and persists active pharmacy',
    () async {
      final storage = FakeAccountStorage(token: 'token', activePharmacyId: 1);
      final api = FakeAccountApi();
      final repository = AccountRepository(api, storage, storage);

      final session = await repository.updateProfile(
        name: 'Updated Owner',
        phone: '0999000000',
      );

      expect(session.actor.name, 'Updated Owner');
      expect(session.actor.phone, '0999000000');
      expect(
        session.actor.profileImageUrl,
        'https://api.test/storage/profiles/photo.png',
      );
      expect(storage.activePharmacyId, 7);
      expect(
        api.profileData?.fields.any((field) => field.key == 'email'),
        isFalse,
      );
      expect(api.profileCalls, 1);
    },
  );

  test(
    'pharmacy clear sends both coordinates as explicit null values',
    () async {
      final storage = FakeAccountStorage(token: 'token', activePharmacyId: 7);
      final api = FakeAccountApi();
      final repository = AccountRepository(api, storage, storage);

      await repository.updatePharmacy(
        name: 'Pharmacy',
        address: 'Address',
        includeCoordinates: true,
        latitude: null,
        longitude: null,
      );

      expect(api.pharmacyData?['latitude'], isNull);
      expect(api.pharmacyData?.containsKey('latitude'), isTrue);
      expect(api.pharmacyData?['longitude'], isNull);
      expect(api.pharmacyData?.containsKey('longitude'), isTrue);
    },
  );

  test('omitted coordinates are not sent by pharmacy update', () async {
    final storage = FakeAccountStorage(token: 'token', activePharmacyId: 7);
    final api = FakeAccountApi();
    final repository = AccountRepository(api, storage, storage);

    await repository.updatePharmacy(name: 'Pharmacy', address: 'Address');

    expect(api.pharmacyData?.containsKey('latitude'), isFalse);
    expect(api.pharmacyData?.containsKey('longitude'), isFalse);
  });

  test(
    'deactivation success clears app, pharmacy, and status credentials',
    () async {
      final storage = FakeAccountStorage(
        token: 'token',
        activePharmacyId: 7,
        registrationStatusToken: 'status-token',
      );
      final repository = AccountRepository(FakeAccountApi(), storage, storage);

      await repository.deactivateAccount('password');

      expect(storage.token, isNull);
      expect(storage.activePharmacyId, isNull);
      expect(storage.registrationStatusToken, isNull);
    },
  );

  test('failed deactivation preserves the local session', () async {
    final storage = FakeAccountStorage(
      token: 'token',
      activePharmacyId: 7,
      registrationStatusToken: 'status-token',
    );
    final api = FakeAccountApi()..deactivationFails = true;
    final repository = AccountRepository(api, storage, storage);

    await expectLater(
      repository.deactivateAccount('password'),
      throwsA(isA<AuthApiException>()),
    );

    expect(storage.token, 'token');
    expect(storage.activePharmacyId, 7);
    expect(storage.registrationStatusToken, 'status-token');
  });

  test('password change sends all three dedicated fields', () async {
    final storage = FakeAccountStorage(token: 'token');
    final api = FakeAccountApi();
    final repository = AccountRepository(api, storage, storage);

    await repository.changePassword(
      currentPassword: 'old-password',
      newPassword: 'new-password',
      confirmation: 'new-password',
    );

    expect(api.passwordData, {
      'current_password': 'old-password',
      'new_password': 'new-password',
      'new_password_confirmation': 'new-password',
    });
  });
}

class FakeAccountApi implements AccountRemoteDataSource {
  int profileCalls = 0;
  FormData? profileData;
  Map<String, dynamic>? pharmacyData;
  Map<String, dynamic>? passwordData;
  bool deactivationFails = false;

  @override
  Future<Response<dynamic>> updateProfile(FormData data) async {
    profileCalls++;
    profileData = data;
    final fields = {for (final field in data.fields) field.key: field.value};
    return _sessionResponse(
      name: fields['name'] ?? 'Owner',
      phone: fields['phone'],
    );
  }

  @override
  Future<Response<dynamic>> updatePharmacy(Map<String, dynamic> data) async {
    pharmacyData = data;
    return _sessionResponse();
  }

  @override
  Future<Response<dynamic>> changePassword(Map<String, dynamic> data) async {
    passwordData = data;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/password/change'),
      data: {'code': 'password_changed'},
    );
  }

  @override
  Future<Response<dynamic>> deactivateAccount(Map<String, dynamic> data) async {
    if (deactivationFails) {
      throw DioException(
        requestOptions: RequestOptions(path: '/account/deactivate'),
        type: DioExceptionType.connectionError,
      );
    }
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/account/deactivate'),
      data: {'code': 'account_deactivated'},
    );
  }
}

Response<dynamic> _sessionResponse({
  String name = 'Owner',
  String? phone = '0999000000',
}) {
  final pharmacy = {
    'id': 7,
    'name': 'Pharmacy',
    'address': 'Address',
    'status': 'approved',
    'latitude': 33.5,
    'longitude': 36.2,
    'certificate_on_file': true,
    'license_on_file': true,
  };
  return Response<dynamic>(
    requestOptions: RequestOptions(path: '/profile/update'),
    data: {
      'data': {
        'session': {
          'actor': {
            'id': 1,
            'type': 'pharmacist',
            'role': 'owner',
            'status': null,
            'name': name,
            'email': 'owner@example.test',
            'phone': phone,
            'profile_image_url': 'https://api.test/storage/profiles/photo.png',
          },
          'available_pharmacies': [pharmacy],
          'active_pharmacy': pharmacy,
          'access': {
            'operational': true,
            'code': 'ready',
            'requires_active_pharmacy': false,
          },
        },
      },
    },
  );
}

class FakeAccountStorage implements SessionStorage, RegistrationStatusStorage {
  String? token;
  int? activePharmacyId;
  String? registrationStatusToken;

  FakeAccountStorage({
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
