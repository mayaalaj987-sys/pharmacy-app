import 'package:dio/dio.dart';

import '../storage/secure_storage_service.dart';
import 'api_constants.dart';
import 'auth_interceptor.dart';
import 'auth_session_events.dart';

class DioClient {
  static late Dio dio;

  static void init(SessionStorage storage, AuthSessionEvents sessionEvents) {
    dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {'Accept': 'application/json'},
      ),
    );

    dio.interceptors.add(AuthInterceptor(storage, sessionEvents));
  }
}
