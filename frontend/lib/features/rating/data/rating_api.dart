import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'rating_remote_data_source.dart';

class RatingApi implements RatingRemoteDataSource {
  final Dio dio;

  RatingApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getMyRating() => dio.get(ApiConstants.rating);

  @override
  Future<Response<dynamic>> submitRating(Map<String, dynamic> data) {
    return dio.post(ApiConstants.rating, data: data);
  }
}
