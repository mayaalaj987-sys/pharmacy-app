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

  Future<List<CategoryRevenue>> fetchCategoryRevenue(String filter) async {
    try {
      final data = await _map(api.getMostSoldByCategory(filter));
      final raw = data['categories'];
      if (raw is! List) return const <CategoryRevenue>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(CategoryRevenue.fromJson)
          .toList(growable: false);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<SalesAverage> fetchAverageSales(String filter) async {
    try {
      return SalesAverage.fromJson(await _map(api.getAverageSales(filter)));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<CashFlow> fetchCashFlow(String filter) async {
    try {
      return CashFlow.fromJson(await _map(api.getCashFlow(filter)));
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<List<PaymentMethodShare>> fetchPaymentMethods(String filter) async {
    try {
      final data = await _map(api.getPaymentMethods(filter));
      final raw = data['methods'];
      if (raw is! List) return const <PaymentMethodShare>[];
      return raw
          .whereType<Map<String, dynamic>>()
          .map(PaymentMethodShare.fromJson)
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
