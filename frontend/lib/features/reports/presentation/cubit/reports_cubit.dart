import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/reports_repository.dart';
import '../../domain/reports.dart';
import 'reports_state.dart';

class ReportsCubit extends Cubit<ReportsState> {
  final ReportsRepository repository;

  static const revenueFilters = ['daily', 'weekly', 'monthly', 'yearly'];

  ReportsCubit(this.repository) : super(const ReportsState.initial());

  /// Home dashboard: today's summary plus revenue per period for the chart.
  Future<void> loadDashboard() async {
    if (state.status == ReportsStatus.loading) return;
    emit(state.copyWith(status: ReportsStatus.loading, clearError: true));
    try {
      final summary = await repository.fetchDashboard();
      final points = await Future.wait(
        revenueFilters.map(repository.fetchRevenue),
      );

      emit(
        state.copyWith(
          status: ReportsStatus.ready,
          dashboard: summary,
          revenueByFilter: {
            for (final point in points) point.filter: point.revenue,
          },
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: ReportsStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: ReportsStatus.failure,
          error: const AuthApiException(message: 'Unable to load dashboard.'),
        ),
      );
    }
  }

  /// Analytics screen: profit breakdown, inventory value and best sellers.
  Future<void> loadAnalytics({String? filter}) async {
    final selected = filter ?? state.analyticsFilter;
    if (state.analyticsStatus == ReportsStatus.loading) return;
    emit(
      state.copyWith(
        analyticsStatus: ReportsStatus.loading,
        analyticsFilter: selected,
        clearAnalyticsError: true,
      ),
    );
    try {
      // Fetched together rather than one after another: seven sequential
      // round trips on a Syrian mobile connection is a screen that appears to
      // hang, and none of these depends on another.
      final results = await Future.wait([
        repository.fetchProfits(selected),
        repository.fetchInventoryValue(),
        repository.fetchMostSold(selected),
        repository.fetchCashFlow(selected),
        repository.fetchAverageSales(selected),
        repository.fetchPaymentMethods(selected),
        repository.fetchCategoryRevenue(selected),
      ]);

      // The period chart compares all four windows, so it needs all four —
      // and this screen can be opened without ever visiting the dashboard
      // that used to be the only thing loading them.
      final points = await Future.wait(
        revenueFilters.map(repository.fetchRevenue),
      );

      emit(
        state.copyWith(
          analyticsStatus: ReportsStatus.ready,
          revenueByFilter: {
            for (final point in points) point.filter: point.revenue,
          },
          profits: results[0] as ProfitReport,
          inventoryValue: results[1] as InventoryValue,
          mostSold: results[2] as List<MostSoldMedicine>,
          cashFlow: results[3] as CashFlow,
          salesAverage: results[4] as SalesAverage,
          paymentMethods: results[5] as List<PaymentMethodShare>,
          categoryRevenue: results[6] as List<CategoryRevenue>,
        ),
      );
    } on AuthApiException catch (error) {
      emit(
        state.copyWith(
          analyticsStatus: ReportsStatus.failure,
          analyticsError: error,
        ),
      );
    } catch (_) {
      emit(
        state.copyWith(
          analyticsStatus: ReportsStatus.failure,
          analyticsError: const AuthApiException(
            message: 'Unable to load analytics.',
          ),
        ),
      );
    }
  }
}
