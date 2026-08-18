import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/purchase_cart.dart';

enum PurchaseCartStatus { initial, loading, ready, failure }

class PurchaseCartState {
  final PurchaseCartStatus status;
  final PurchaseCart cart;
  final AuthApiException? error;

  /// The cart line currently being changed, so only its row shows a spinner.
  /// `-1` stands for a whole-cart action: clearing, or buying.
  final int? mutatingItemId;

  const PurchaseCartState({
    this.status = PurchaseCartStatus.initial,
    this.cart = const PurchaseCart.empty(),
    this.error,
    this.mutatingItemId,
  });

  const PurchaseCartState.initial() : this();

  bool get busy => mutatingItemId != null;

  PurchaseCartState copyWith({
    PurchaseCartStatus? status,
    PurchaseCart? cart,
    AuthApiException? error,
    int? mutatingItemId,
    bool clearError = false,
    bool clearMutating = false,
  }) {
    return PurchaseCartState(
      status: status ?? this.status,
      cart: cart ?? this.cart,
      error: clearError ? null : (error ?? this.error),
      mutatingItemId: clearMutating
          ? null
          : (mutatingItemId ?? this.mutatingItemId),
    );
  }
}
