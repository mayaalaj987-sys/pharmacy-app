import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import 'rating_remote_data_source.dart';

/// The signed-in pharmacist's app rating plus the overall average.
class AppRating {
  final bool hasRated;
  final int? myStars;
  final double averageStars;
  final int ratingsCount;

  const AppRating({
    required this.hasRated,
    required this.averageStars,
    required this.ratingsCount,
    this.myStars,
  });

  static const empty = AppRating(
    hasRated: false,
    averageStars: 0,
    ratingsCount: 0,
  );
}

class RatingRepository {
  final RatingRemoteDataSource api;

  const RatingRepository(this.api);

  Future<AppRating> fetchMyRating() async {
    try {
      final response = await api.getMyRating();
      final data = response.data;
      if (data is! Map<String, dynamic>) return AppRating.empty;

      final rating = data['rating'];
      final stars = rating is Map<String, dynamic> ? rating['stars'] : null;

      return AppRating(
        hasRated: data['has_rated'] == true,
        myStars: stars == null
            ? null
            : (stars is num ? stars.toInt() : int.tryParse(stars.toString())),
        averageStars: _toDouble(data['average_stars']),
        ratingsCount: _toInt(data['ratings_count']),
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// [pharmacistId] must be the authenticated pharmacist; the backend rejects
  /// any mismatch, so it is taken from the session rather than user input.
  Future<void> submitRating({
    required int pharmacistId,
    required int stars,
  }) async {
    try {
      await api.submitRating({'pharmacist_id': pharmacistId, 'stars': stars});
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

  static int _toInt(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
}
