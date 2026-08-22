import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../auth/data/models/auth_api_exception.dart';
import 'support_remote_data_source.dart';

class SupportApi implements SupportRemoteDataSource {
  final Dio dio;
  final RegistrationStatusStorage? registrationStatusStorage;

  SupportApi({Dio? dio})
    : dio = dio ?? DioClient.dio,
      registrationStatusStorage = null;

  SupportApi.registration({
    Dio? dio,
    RegistrationStatusStorage? registrationStatusStorage,
  }) : dio = dio ?? DioClient.dio,
       registrationStatusStorage = registrationStatusStorage ?? TokenStorage();

  /// Support is reachable without an active pharmacy: someone whose pharmacy
  /// context is broken is exactly who needs to write in.
  @override
  Future<Response<dynamic>> getTickets() async {
    final options = await _options();
    return dio.get(
      registrationStatusStorage == null
          ? ApiConstants.supportTickets
          : ApiConstants.registrationSupportTickets,
      options: options,
    );
  }

  @override
  Future<Response<dynamic>> createTicket(Map<String, dynamic> data) async {
    final options = await _options();
    return dio.post(
      registrationStatusStorage == null
          ? ApiConstants.supportTickets
          : ApiConstants.registrationSupportTickets,
      data: data,
      options: options,
    );
  }

  Future<Options> _options() async {
    final storage = registrationStatusStorage;
    if (storage == null) {
      return Options(extra: {'skipActivePharmacy': true});
    }

    final token = await storage.getRegistrationStatusToken();
    if (token == null || token.isEmpty) {
      throw const AuthApiException(
        message: 'Registration support access is no longer available.',
        code: 'registration_status_unauthenticated',
        statusCode: 401,
      );
    }

    return Options(
      headers: {'Authorization': 'Bearer $token'},
      extra: {'skipAuthentication': true, 'skipActivePharmacy': true},
    );
  }
}
