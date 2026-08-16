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
  Future<bool> createSale({
    required List<Map<String, dynamic>> items,
    required String paymentMethod,
    String? customerName,
    String? cardNumber,
  }) async {
    if (state.submitting) return false;
    emit(state.copyWith(submitting: true, clearError: true));
    try {
      await repository.createSale(
        items: items,
        paymentMethod: paymentMethod,
        customerName: customerName,
        cardNumber: cardNumber,
      );
      final summary = await repository.fetchAllSales();
      emit(
        state.copyWith(
          status: SalesStatus.ready,
          sales: summary.sales,
          totalSales: summary.totalSales,
          totalPrice: summary.totalPrice,
          submitting: false,
        ),
      );
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
