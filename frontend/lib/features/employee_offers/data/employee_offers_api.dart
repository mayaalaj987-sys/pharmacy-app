import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'employee_offers_remote_data_source.dart';

class EmployeeOffersApi implements EmployeeOffersRemoteDataSource {
  final Dio dio;

  EmployeeOffersApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  /// `skipActivePharmacy` is load-bearing rather than tidiness: someone reading
  /// their offers has no pharmacy, and a just-resigned employee still has a
  /// stale id in storage that the interceptor would otherwise attach — earning
  /// a 403 on the one screen they need.
  @override
  Future<Response<dynamic>> getOffers() {
    return dio.get(
      ApiConstants.employeeOffers,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> acceptOffer(int id) {
    return dio.post(
      '${ApiConstants.employeeOffers}/$id/accept',
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }
}
