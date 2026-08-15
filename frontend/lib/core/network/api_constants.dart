class ApiConstants {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  static const String login = "/login";
  static const String employeeLogin = "/employee/login";
  static const String registerPharmacist = "/register";
  static const String registerEmployee = "/employee/register";
  static const String registrationStatus = "/registration/status";
  static const String me = "/me";
  static const String logout = "/logout";
}
