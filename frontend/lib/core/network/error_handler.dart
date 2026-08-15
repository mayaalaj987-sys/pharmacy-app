import 'package:dio/dio.dart';

class ErrorHandler {
  static String handle(DioException e) {
    print("STATUS: ${e.response?.statusCode}");
    print("DATA: ${e.response?.data}");

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
        return "Connection timeout";
      case DioExceptionType.receiveTimeout:
        return "Server not responding";
      case DioExceptionType.badResponse:
        if (e.response?.statusCode == 422) {
          final errors = e.response?.data["errors"];
          if (errors != null) {
            return errors.values.first[0];
          }
        }
        return e.response?.data["message"] ?? "Server error";
      case DioExceptionType.connectionError:
        return "No internet connection";
      default:
        return "Unexpected error";
    }
  }
}