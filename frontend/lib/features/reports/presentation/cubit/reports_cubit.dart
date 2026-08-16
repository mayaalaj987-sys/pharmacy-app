import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/reports_repository.dart';
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
      final profits = await repository.fetchProfits(selected);
      final inventoryValue = await repository.fetchInventoryValue();
      final mostSold = await repository.fetchMostSold(selected);

      emit(
        state.copyWith(
          analyticsStatus: ReportsStatus.ready,
          profits: profits,
          inventoryValue: inventoryValue,
          mostSold: mostSold,
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
