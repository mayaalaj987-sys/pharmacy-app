import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/network/auth_interceptor.dart';
import 'package:phamacy_managment/core/network/auth_session_events.dart';
import 'package:phamacy_managment/core/storage/secure_storage_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
    'normal interceptor never injects the registration-status token',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = TokenStorage(secureStorage: FakeSecureValueStore());
      await storage.saveRegistrationStatusToken('status-token');
      final adapter = RecordingAdapter();
      final dio = Dio()..httpClientAdapter = adapter;
      dio.interceptors.add(AuthInterceptor(storage, AuthSessionEvents()));

      await dio.get<dynamic>('/me');

      expect(adapter.lastRequest?.headers['Authorization'], isNull);
    },
  );

  test('a status-token 401 does not clear an application session', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = TokenStorage(secureStorage: FakeSecureValueStore());
    await storage.saveToken('app-token');
    await storage.saveActivePharmacyId(9);
    await storage.saveRegistrationStatusToken('status-token');
    final events = AuthSessionEvents();
    var invalidations = 0;
    final subscription = events.invalidated.listen((_) => invalidations++);
    final dio = Dio()
      ..httpClientAdapter = RecordingAdapter(statusCode: 401)
      ..interceptors.add(AuthInterceptor(storage, events));

    await expectLater(
      dio.get<dynamic>(
        '/registration/status',
        options: Options(
          headers: {'Authorization': 'Bearer status-token'},
          extra: {'skipAuthentication': true, 'skipActivePharmacy': true},
        ),
      ),
      throwsA(isA<DioException>()),
    );

    expect(await storage.getToken(), 'app-token');
    expect(await storage.getActivePharmacyId(), 9);
    expect(await storage.getRegistrationStatusToken(), 'status-token');
    expect(invalidations, 0);
    await subscription.cancel();
  });

  test(
    'account_deactivated 403 clears every local credential and notifies',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = TokenStorage(secureStorage: FakeSecureValueStore());
      await storage.saveToken('app-token');
      await storage.saveActivePharmacyId(9);
      await storage.saveRegistrationStatusToken('status-token');
      final events = AuthSessionEvents();
      var invalidations = 0;
      final subscription = events.invalidated.listen((_) => invalidations++);
      final dio = Dio()
        ..httpClientAdapter = RecordingAdapter(
          statusCode: 403,
          responseBody:
              '{"message":"This account has been deactivated.","code":"account_deactivated"}',
        )
        ..interceptors.add(AuthInterceptor(storage, events));

      await expectLater(dio.get<dynamic>('/me'), throwsA(isA<DioException>()));
      await Future<void>.delayed(Duration.zero);

      expect(await storage.getToken(), isNull);
      expect(await storage.getActivePharmacyId(), isNull);
      expect(await storage.getRegistrationStatusToken(), isNull);
      expect(invalidations, 1);
      await subscription.cancel();
    },
  );

  test('a generic 403 does not broaden local session invalidation', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = TokenStorage(secureStorage: FakeSecureValueStore());
    await storage.saveToken('app-token');
    await storage.saveActivePharmacyId(9);
    final events = AuthSessionEvents();
    var invalidations = 0;
    final subscription = events.invalidated.listen((_) => invalidations++);
    final dio = Dio()
      ..httpClientAdapter = RecordingAdapter(
        statusCode: 403,
        responseBody:
            '{"message":"You cannot access this pharmacy.","code":"active_pharmacy_forbidden"}',
      )
      ..interceptors.add(AuthInterceptor(storage, events));

    await expectLater(dio.get<dynamic>('/me'), throwsA(isA<DioException>()));

    expect(await storage.getToken(), 'app-token');
    expect(await storage.getActivePharmacyId(), 9);
    expect(invalidations, 0);
    await subscription.cancel();
  });
}

class RecordingAdapter implements HttpClientAdapter {
  final int statusCode;
  final String? responseBody;
  RequestOptions? lastRequest;

  RecordingAdapter({this.statusCode = 200, this.responseBody});

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    lastRequest = options;
    return ResponseBody.fromString(
      responseBody ??
          (statusCode == 200 ? '{}' : '{"message":"Unauthenticated."}'),
      statusCode,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

class FakeSecureValueStore implements SecureValueStore {
  final Map<String, String> values = {};

  @override
  Future<void> delete(String key) async => values.remove(key);

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;
}
