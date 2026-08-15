import 'package:dio/dio.dart';
import '../storage/secure_storage_service.dart';
import 'auth_interceptor.dart';

class DioClient {

  static late Dio dio;

  static void init(TokenStorage storage) {

    dio = Dio(
      BaseOptions(
        baseUrl: "http://10.0.2.2:8000/api",
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {
          "Accept": "application/json",
        },
      ),
    );

    dio.interceptors.add(AuthInterceptor(storage));
  }
}