import 'package:dio/dio.dart';

abstract class RatingRemoteDataSource {
  Future<Response<dynamic>> getMyRating();

  Future<Response<dynamic>> submitRating(Map<String, dynamic> data);
}
