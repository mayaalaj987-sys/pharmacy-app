import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/sale.dart';

enum SalesStatus { initial, loading, ready, failure }

class SalesState {
  final SalesStatus status;
  final List<Sale> sales;
  final int totalSales;
  final double totalPrice;
  final AuthApiException? error;

  /// True while a sale is being submitted.
  final bool submitting;

  /// Total the backend charged for the most recent sale in this session.
  ///
  /// Taken from the `POST /sale/create` response rather than from [sales],
  /// because that list is only populated by the pharmacist-only sales report
  /// and would otherwise be stale — or empty for an employee.
  final double? lastSaleTotal;

  const SalesState({
    this.status = SalesStatus.initial,
    this.sales = const <Sale>[],
    this.totalSales = 0,
    this.totalPrice = 0,
    this.error,
    this.submitting = false,
    this.lastSaleTotal,
  });

  const SalesState.initial() : this();

  SalesState copyWith({
    SalesStatus? status,
    List<Sale>? sales,
    int? totalSales,
    double? totalPrice,
    AuthApiException? error,
    bool? submitting,
    double? lastSaleTotal,
    bool clearError = false,
  }) {
    return SalesState(
      status: status ?? this.status,
      sales: sales ?? this.sales,
      totalSales: totalSales ?? this.totalSales,
      totalPrice: totalPrice ?? this.totalPrice,
      error: clearError ? null : (error ?? this.error),
      submitting: submitting ?? this.submitting,
      lastSaleTotal: lastSaleTotal ?? this.lastSaleTotal,
    );
  }
}
