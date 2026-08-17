import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'support_remote_data_source.dart';

class SupportApi implements SupportRemoteDataSource {
  final Dio dio;

  SupportApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  /// Support is reachable without an active pharmacy: someone whose pharmacy
  /// context is broken is exactly who needs to write in.
  @override
  Future<Response<dynamic>> getTickets() {
    return dio.get(
      ApiConstants.supportTickets,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> createTicket(Map<String, dynamic> data) {
    return dio.post(
      ApiConstants.supportTickets,
      data: data,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }
}
