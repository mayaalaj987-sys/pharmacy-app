import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/reports.dart';

enum ReportsStatus { initial, loading, ready, failure }

class ReportsState {
  final ReportsStatus status;
  final DashboardSummary dashboard;

  /// Revenue keyed by backend filter (daily/weekly/monthly/yearly).
  final Map<String, double> revenueByFilter;

  final AuthApiException? error;

  /// Analytics screen (loaded separately from the home dashboard).
  final ReportsStatus analyticsStatus;
  final ProfitReport profits;
  final InventoryValue inventoryValue;
  final List<MostSoldMedicine> mostSold;

  /// Liquidity, shown beside profit because they answer different questions
  /// and a pharmacy can be doing well on one and failing on the other.
  final CashFlow cashFlow;

  final SalesAverage salesAverage;
  final List<PaymentMethodShare> paymentMethods;
  final List<CategoryRevenue> categoryRevenue;

  final String analyticsFilter;
  final AuthApiException? analyticsError;

  const ReportsState({
    this.status = ReportsStatus.initial,
    this.dashboard = DashboardSummary.empty,
    this.revenueByFilter = const <String, double>{},
    this.error,
    this.analyticsStatus = ReportsStatus.initial,
    this.profits = ProfitReport.empty,
    this.inventoryValue = InventoryValue.empty,
    this.mostSold = const <MostSoldMedicine>[],
    this.cashFlow = CashFlow.empty,
    this.salesAverage = SalesAverage.empty,
    this.paymentMethods = const <PaymentMethodShare>[],
    this.categoryRevenue = const <CategoryRevenue>[],
    this.analyticsFilter = 'monthly',
    this.analyticsError,
  });

  const ReportsState.initial() : this();

  ReportsState copyWith({
    ReportsStatus? status,
    DashboardSummary? dashboard,
    Map<String, double>? revenueByFilter,
    AuthApiException? error,
    ReportsStatus? analyticsStatus,
    ProfitReport? profits,
    InventoryValue? inventoryValue,
    List<MostSoldMedicine>? mostSold,
    CashFlow? cashFlow,
    SalesAverage? salesAverage,
    List<PaymentMethodShare>? paymentMethods,
    List<CategoryRevenue>? categoryRevenue,
    String? analyticsFilter,
    AuthApiException? analyticsError,
    bool clearError = false,
    bool clearAnalyticsError = false,
  }) {
    return ReportsState(
      status: status ?? this.status,
      dashboard: dashboard ?? this.dashboard,
      revenueByFilter: revenueByFilter ?? this.revenueByFilter,
      error: clearError ? null : (error ?? this.error),
      analyticsStatus: analyticsStatus ?? this.analyticsStatus,
      profits: profits ?? this.profits,
      inventoryValue: inventoryValue ?? this.inventoryValue,
      mostSold: mostSold ?? this.mostSold,
      cashFlow: cashFlow ?? this.cashFlow,
      salesAverage: salesAverage ?? this.salesAverage,
      paymentMethods: paymentMethods ?? this.paymentMethods,
      categoryRevenue: categoryRevenue ?? this.categoryRevenue,
      analyticsFilter: analyticsFilter ?? this.analyticsFilter,
      analyticsError: clearAnalyticsError
          ? null
          : (analyticsError ?? this.analyticsError),
    );
  }
}
