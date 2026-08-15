import 'package:dio/dio.dart';

import '../storage/secure_storage_service.dart';
import 'auth_session_events.dart';

class AuthInterceptor extends Interceptor {
  final SessionStorage storage;
  final AuthSessionEvents sessionEvents;

  AuthInterceptor(this.storage, this.sessionEvents);

  @override
  void onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (options.extra['skipAuthentication'] != true) {
      final token = await storage.getToken();
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }

      if (options.extra['skipActivePharmacy'] != true &&
          options.headers['X-Pharmacy-Id'] == null) {
        final pharmacyId = await storage.getActivePharmacyId();
        if (pharmacyId != null) {
          options.headers['X-Pharmacy-Id'] = pharmacyId;
        }
      }
    }

    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final wasAuthenticated =
        err.requestOptions.headers['Authorization'] != null;
    if (err.response?.statusCode == 401 && wasAuthenticated) {
      await storage.clearSession();
      sessionEvents.notifyInvalidated();
    }

    handler.next(err);
  }
}
