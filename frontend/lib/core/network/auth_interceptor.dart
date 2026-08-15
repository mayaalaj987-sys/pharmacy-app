import 'package:dio/dio.dart';

import '../storage/secure_storage_service.dart';


class AuthInterceptor extends Interceptor {
  final TokenStorage storage;

  AuthInterceptor(this.storage);

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await storage.getToken();

    if (token != null) {
      options.headers["Authorization"] = "Bearer $token";
    }

    return handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      // ممكن logout تلقائي لاحقاً
    }

    handler.next(err);
  }
}