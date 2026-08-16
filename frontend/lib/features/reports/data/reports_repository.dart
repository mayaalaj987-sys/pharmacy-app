import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/reports.dart';

import 'reports_remote_data_source.dart';

class ReportsRepository {
  final ReportsRemoteDataSource api;

  const ReportsRepository(this.api);

  Future<DashboardSummary> fetchDashboard() async {
    try {
      final data = await _map(api.getDashboard());
      return DashboardSummary.fromJson(data);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<RevenuePoint> fetchRevenue(String filter) async {
    try {
      return RevenuePoint.fromJson(await _map(api.getRevenue(filter)));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<ProfitReport> fetchProfits(String filter) async {
    try {
      return ProfitReport.fromJson(await _map(api.getProfits(filter)));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<InventoryValue> fetchInventoryValue() async {
    try {
      return InventoryValue.fromJson(await _map(api.getInventoryValue()));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<List<MostSoldMedicine>> fetchMostSold(String filter) async {
    try {
      final data = await _map(api.getMostSold(filter));
      final raw = data['medicines'];
      if (raw is! List) return const <MostSoldMedicine>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(MostSoldMedicine.fromJson)
          .toList(growable: false);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<Map<String, dynamic>> _map(Future<Response<dynamic>> call) async {
    final response = await call;
    final data = response.data;
    return data is Map<String, dynamic> ? data : <String, dynamic>{};
  }
}
