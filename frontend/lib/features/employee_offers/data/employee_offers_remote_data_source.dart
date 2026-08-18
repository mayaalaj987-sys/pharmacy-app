import 'package:dio/dio.dart';

abstract class EmployeeOffersRemoteDataSource {
  Future<Response<dynamic>> getOffers();

  /// Body is empty by contract: none of the terms are the applicant's
  /// to set, so accepting cannot rewrite what was offered.
  Future<Response<dynamic>> acceptOffer(int id);

  /// Leaves the current job. Frees the shift and puts the person back in
  /// the pool with their old offers live again.
  Future<Response<dynamic>> resign();
}
