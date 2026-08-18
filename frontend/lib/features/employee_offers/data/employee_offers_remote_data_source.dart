import 'package:dio/dio.dart';

abstract class EmployeeOffersRemoteDataSource {
  Future<Response<dynamic>> getOffers();

  /// Body is empty by contract: none of the terms are the applicant's
  /// to set, so accepting cannot rewrite what was offered.
  Future<Response<dynamic>> acceptOffer(int id);
}
