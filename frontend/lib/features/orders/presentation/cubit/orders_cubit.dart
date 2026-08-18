import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/orders_repository.dart';
import '../../domain/receiving_plan.dart';
import 'orders_state.dart';

/// Orders once they exist: listed, received, cancelled.
///
/// Deliberately cannot create one. Orders are placed by checking out the
/// purchase cart, which is the only path that lets the pharmacist compare
/// suppliers first — a second way in would be a second copy of the reservation
/// rules, quietly diverging.
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

  /// What is about to arrive, so the pharmacist can price it before it lands.
  ///
  /// Not held in state: it is read once, shown in a sheet and acted on. Keeping
  /// it would only let a stale copy be received against.
  Future<ReceivingPlan?> receivingPlan(int id) async {
    try {
      return await repository.fetchReceivingPlan(id);
    } catch (_) {
      return null;
    }
  }

  Future<bool> receiveOrder(int id, {Map<int, double>? sellingPrices}) =>
      _mutate(
        id,
        () => repository.receiveOrder(id, sellingPrices: sellingPrices),
      );

  Future<bool> cancelOrder(int id) =>
      _mutate(id, () => repository.cancelOrder(id));

  /// Runs a write then reloads authoritative server state. Single-flight per order.
  Future<bool> _mutate(int? orderId, Future<void> Function() action) async {
    if (state.mutatingOrderId != null) return false;
    emit(state.copyWith(mutatingOrderId: orderId ?? -1, clearError: true));
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
          error: const AuthApiException(message: 'Unable to update the order.'),
          clearMutating: true,
        ),
      );
      return false;
    }
  }
}
