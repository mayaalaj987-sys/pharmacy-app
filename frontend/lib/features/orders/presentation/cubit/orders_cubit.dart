import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/orders_repository.dart';
import 'orders_state.dart';

class OrdersCubit extends Cubit<OrdersState> {
  final OrdersRepository repository;

  OrdersCubit(this.repository) : super(const OrdersState.initial());

  Future<void> load() async {
    if (state.status == OrdersStatus.loading) return;
    emit(state.copyWith(status: OrdersStatus.loading, clearError: true));
    try {
      final orders = await repository.fetchOrders();
      emit(state.copyWith(status: OrdersStatus.ready, orders: orders));
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: OrdersStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: OrdersStatus.failure,
          error: const AuthApiException(message: 'Unable to load orders.'),
        ),
      );
    }
  }

  Future<bool> createOrder({
    required int supplierId,
    required int medicineId,
    required int quantity,
    String paymentMethod = 'cash',
  }) {
    return _mutate(
      null,
      () => repository.createOrder(
        supplierId: supplierId,
        items: [
          {'medicine_id': medicineId, 'quantity': quantity},
        ],
        paymentMethod: paymentMethod,
      ),
    );
  }

  Future<bool> receiveOrder(int id) =>
      _mutate(id, () => repository.receiveOrder(id));

  Future<bool> cancelOrder(int id) =>
      _mutate(id, () => repository.cancelOrder(id));

  /// Runs a write then reloads authoritative server state. Single-flight per order.
  Future<bool> _mutate(int? orderId, Future<void> Function() action) async {
    if (state.mutatingOrderId != null) return false;
    emit(
      state.copyWith(
        mutatingOrderId: orderId ?? -1,
        clearError: true,
      ),
    );
    try {
      await action();
      final orders = await repository.fetchOrders();
      emit(
        state.copyWith(
          status: OrdersStatus.ready,
          orders: orders,
          clearMutating: true,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(error: error, clearMutating: true));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(
            message: 'Unable to update the order.',
          ),
          clearMutating: true,
        ),
      );
      return false;
    }
  }
}
