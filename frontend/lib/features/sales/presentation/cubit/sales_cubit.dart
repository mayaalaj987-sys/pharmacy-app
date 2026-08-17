import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/sales_repository.dart';
import 'sales_state.dart';

class SalesCubit extends Cubit<SalesState> {
  final SalesRepository repository;

  SalesCubit(this.repository) : super(const SalesState.initial());

  Future<void> load({String? filter}) async {
    if (state.status == SalesStatus.loading) return;
    emit(state.copyWith(status: SalesStatus.loading, clearError: true));
    try {
      final summary = await repository.fetchAllSales(filter: filter);
      emit(
        state.copyWith(
          status: SalesStatus.ready,
          sales: summary.sales,
          totalSales: summary.totalSales,
          totalPrice: summary.totalPrice,
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: SalesStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: SalesStatus.failure,
          error: const AuthApiException(message: 'Unable to load sales.'),
        ),
      );
    }
  }

  /// Submits a sale. The backend validates stock and applies any insurance
  /// discount inside its transaction; we never adjust stock locally.
  ///
  /// Deliberately does **not** refresh the sales summary afterwards.
  /// `fetchAllSales` hits `GET /sale/all`, which is pharmacist-only: an
  /// employee selling at the point of sale would get a 401 there, and the auth
  /// interceptor reads any 401 as an expired session and signs them out — after
  /// the sale had already been recorded. Sales History reloads itself in its
  /// own `initState`, and POS refreshes stock through `InventoryCubit`, so
  /// nothing depends on refetching here.
  Future<bool> createSale({
    required List<Map<String, dynamic>> items,
    required String paymentMethod,
    String? customerName,
    String? cardNumber,
  }) async {
    if (state.submitting) return false;
    emit(state.copyWith(submitting: true, clearError: true));
    try {
      final chargedTotal = await repository.createSale(
        items: items,
        paymentMethod: paymentMethod,
        customerName: customerName,
        cardNumber: cardNumber,
      );
      emit(state.copyWith(submitting: false, lastSaleTotal: chargedTotal));
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(submitting: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          submitting: false,
          error: const AuthApiException(
            message: 'Unable to complete the sale.',
          ),
        ),
      );
      return false;
    }
  }
}
