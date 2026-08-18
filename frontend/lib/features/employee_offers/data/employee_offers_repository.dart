import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/job_offer.dart';
import 'employee_offers_remote_data_source.dart';

class EmployeeOffersRepository {
  final EmployeeOffersRemoteDataSource api;

  const EmployeeOffersRepository(this.api);

  Future<JobOfferInbox> fetchInbox() async {
    try {
      final response = await api.getOffers();
      final data = response.data;

      return data is Map<String, dynamic>
          ? JobOfferInbox.fromJson(data)
          : const JobOfferInbox();
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
