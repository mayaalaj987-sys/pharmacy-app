import 'package:dio/dio.dart';

import '../../features/auth/data/models/auth_api_exception.dart';

class ErrorHandler {
  static AuthApiException fromDio(DioException error) {
    final data = error.response?.data;
    final body = data is Map ? data : const {};
    final fieldErrors = <String, List<String>>{};
    final rawErrors = body['errors'];

    if (rawErrors is Map) {
      for (final entry in rawErrors.entries) {
        final value = entry.value;
        fieldErrors[entry.key.toString()] = value is List
            ? value.map((item) => item.toString()).toList()
            : [value.toString()];
      }
    }

    final message = switch (error.type) {
      DioExceptionType.connectionTimeout => 'Connection timeout',
      DioExceptionType.receiveTimeout => 'Server not responding',
      DioExceptionType.connectionError => 'No internet connection',
      _ => body['message']?.toString() ?? 'Unexpected server error',
    };

    return AuthApiException(
      message: message,
      code: body['code']?.toString(),
      statusCode: error.response?.statusCode,
      fieldErrors: fieldErrors,
    );
  }
}
