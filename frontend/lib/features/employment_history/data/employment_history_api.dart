import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/network/error_handler.dart';
import '../domain/employment_record.dart';

/// Work history and the verdict each side owes on it.
///
/// One class for both directions because they are one rule seen from two ends:
/// you may rate a job you held, once it has ended, once.
class EmploymentHistoryApi {
  final Dio dio;

  EmploymentHistoryApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  /// The employee's own history. Outside the pharmacy gate: someone between
  /// jobs still has a past worth showing.
  Future<List<EmploymentRecord>> myHistory() =>
      _list('/employee/employments', skipPharmacy: true);

  Future<void> ratePharmacy(int employmentId, int stars) => _rate(
    '/employee/employments/$employmentId/rate',
    stars,
    skipPharmacy: true,
  );

  /// Everyone who has worked at the pharmacist's active pharmacy.
  Future<List<EmploymentRecord>> pharmacyHistory() =>
      _list('${ApiConstants.employees}/history');

  Future<void> rateEmployee(int employmentId, int stars) =>
      _rate('${ApiConstants.employees}/employments/$employmentId/rate', stars);

  Future<List<EmploymentRecord>> _list(
    String path, {
    bool skipPharmacy = false,
  }) async {
    try {
      final response = await dio.get(
        path,
        options: skipPharmacy
            ? Options(extra: {'skipActivePharmacy': true})
            : null,
      );
      final data = response.data;
      if (data is! Map<String, dynamic>) return const <EmploymentRecord>[];
      final raw = data['employments'];

      return raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(EmploymentRecord.fromJson)
                .toList(growable: false)
          : const <EmploymentRecord>[];
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> _rate(
    String path,
    int stars, {
    bool skipPharmacy = false,
  }) async {
    try {
      await dio.post(
        path,
        data: {'stars': stars},
        options: skipPharmacy
            ? Options(extra: {'skipActivePharmacy': true})
            : null,
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
