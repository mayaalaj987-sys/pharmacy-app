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

  const SalesState({
    this.status = SalesStatus.initial,
    this.sales = const <Sale>[],
    this.totalSales = 0,
    this.totalPrice = 0,
    this.error,
    this.submitting = false,
  });

  const SalesState.initial() : this();

  SalesState copyWith({
    SalesStatus? status,
    List<Sale>? sales,
    int? totalSales,
    double? totalPrice,
    AuthApiException? error,
    bool? submitting,
    bool clearError = false,
  }) {
    return SalesState(
      status: status ?? this.status,
      sales: sales ?? this.sales,
      totalSales: totalSales ?? this.totalSales,
      totalPrice: totalPrice ?? this.totalPrice,
      error: clearError ? null : (error ?? this.error),
      submitting: submitting ?? this.submitting,
    );
  }
}
