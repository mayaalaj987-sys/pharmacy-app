import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/purchase_order.dart';

enum OrdersStatus { initial, loading, ready, failure }

class OrdersState {
  final OrdersStatus status;
  final List<PurchaseOrder> orders;
  final AuthApiException? error;

  /// Id of the order currently being received/cancelled, if any.
  final int? mutatingOrderId;

  const OrdersState({
    this.status = OrdersStatus.initial,
    this.orders = const <PurchaseOrder>[],
    this.error,
    this.mutatingOrderId,
  });

  const OrdersState.initial() : this();

  int countByStatus(String status) =>
      orders.where((order) => order.status == status).length;

  OrdersState copyWith({
    OrdersStatus? status,
    List<PurchaseOrder>? orders,
    AuthApiException? error,
    int? mutatingOrderId,
    bool clearError = false,
    bool clearMutating = false,
  }) {
    return OrdersState(
      status: status ?? this.status,
      orders: orders ?? this.orders,
      error: clearError ? null : (error ?? this.error),
      mutatingOrderId:
          clearMutating ? null : (mutatingOrderId ?? this.mutatingOrderId),
    );
  }
}
