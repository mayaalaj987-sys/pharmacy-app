class AuthApiException implements Exception {
  final String message;
  final String? code;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;

  const AuthApiException({
    required this.message,
    this.code,
    this.statusCode,
    this.fieldErrors = const {},
  });

  @override
  String toString() => message;
}
