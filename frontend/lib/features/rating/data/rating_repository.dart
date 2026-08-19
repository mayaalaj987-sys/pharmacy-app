import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import 'rating_remote_data_source.dart';

/// The signed-in pharmacist's app rating plus the overall average.
class AppRating {
  final bool hasRated;
  final int? myStars;

  /// What they wanted to say. A star records that somebody was unhappy without
  /// recording why, which is the one thing feedback has to do.
  final String? myNote;

  final double averageStars;
  final int ratingsCount;

  const AppRating({
    required this.hasRated,
    required this.averageStars,
    required this.ratingsCount,
    this.myStars,
    this.myNote,
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
      final note = rating is Map<String, dynamic> ? rating['note'] : null;

      return AppRating(
        hasRated: data['has_rated'] == true,
        myStars: stars == null
            ? null
            : (stars is num ? stars.toInt() : int.tryParse(stars.toString())),
        myNote: note?.toString(),
        averageStars: _toDouble(data['average_stars']),
        ratingsCount: _toInt(data['ratings_count']),
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Leaves or revises the rating.
  ///
  /// [pharmacistId] must be the authenticated pharmacist; the backend rejects
  /// any mismatch, so it is taken from the session rather than user input. An
  /// empty note is omitted rather than sent as a blank string, which would read
  /// as "they explained" in the feedback anyone reads later.
  Future<void> submitRating({
    required int pharmacistId,
    required int stars,
    String? note,
  }) async {
    try {
      await api.submitRating({
        'pharmacist_id': pharmacistId,
        'stars': stars,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

  static int _toInt(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
}
