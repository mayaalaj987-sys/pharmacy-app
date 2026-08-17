import 'package:dio/dio.dart';

abstract class AuthRemoteDataSource {
  Future<Response<dynamic>> registerPharmacist(FormData data);

  Future<Response<dynamic>> registrationStatus(String statusToken);

  Future<Response<dynamic>> registerEmployee(FormData data);

  Future<Response<dynamic>> login(String email, String password);

  Future<Response<dynamic>> employeeLogin(String email, String password);

  Future<Response<dynamic>> me({int? activePharmacyId});

  /// Multipart submission of an additional pharmacy for the signed-in owner.
  Future<Response<dynamic>> addPharmacy(FormData data);

  Future<Response<dynamic>> logout();

  Future<Response<dynamic>> logoutRegistrationStatus(String statusToken);
}
