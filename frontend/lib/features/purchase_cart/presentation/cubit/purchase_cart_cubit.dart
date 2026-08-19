import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/purchase_cart_repository.dart';
import 'purchase_cart_state.dart';

/// The cart, which the whole purchasing flow reads from.
///
/// Long-lived rather than created per screen: the badge on the supplier list,
/// the cart page and the sale flow all need the same count, and a second copy
/// would immediately start disagreeing with the first.
class PurchaseCartCubit extends Cubit<PurchaseCartState> {
  final PurchaseCartRepository repository;

  PurchaseCartCubit(this.repository) : super(const PurchaseCartState.initial());

  Future<void> load() async {
    if (state.status == PurchaseCartStatus.loading) return;
    emit(state.copyWith(status: PurchaseCartStatus.loading, clearError: true));

    try {
      emit(
        state.copyWith(
          status: PurchaseCartStatus.ready,
          cart: await repository.fetch(),
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: PurchaseCartStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: PurchaseCartStatus.failure,
          error: const AuthApiException(message: 'Unable to load the cart.'),
        ),
      );
    }
  }

  Future<bool> add(int medicineId, int quantity) =>
      _mutate(-1, () => repository.add(medicineId, quantity));

  Future<bool> setQuantity(int itemId, int quantity) =>
      _mutate(itemId, () => repository.setQuantity(itemId, quantity));

  Future<bool> remove(int itemId) =>
      _mutate(itemId, () => repository.remove(itemId));

  Future<bool> clear() => _mutate(-1, () => repository.clear());

  /// Moves a line onto another supplier's offer of the same drug.
  Future<bool> switchSupplier(int itemId, int medicineId) =>
      _mutate(itemId, () => repository.switchSupplier(itemId, medicineId));

  /// Buys the cart. Returns how many orders it became, or null if it failed.
  ///
  /// All or nothing on the server, so a failure leaves the cart exactly as it
  /// was and the pharmacist can fix one line and try again.
  Future<int?> checkout(String paymentMethod, {String? cardNumber}) async {
    if (state.busy) return null;
    emit(state.copyWith(mutatingItemId: -1, clearError: true));

    try {
      final orders = await repository.checkout(
        paymentMethod,
        cardNumber: cardNumber,
      );
      emit(
        state.copyWith(
          status: PurchaseCartStatus.ready,
          cart: await repository.fetch(),
          clearMutating: true,
        ),
      );

      return orders;
    } on AuthApiException catch (error) {
      emit(state.copyWith(error: error, clearMutating: true));

      return null;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(
            message: 'Your order could not be placed.',
          ),
          clearMutating: true,
        ),
      );

      return null;
    }
  }

  /// Runs a write and takes the cart it answers with. Single-flight.
  Future<bool> _mutate(int itemId, Future<dynamic> Function() action) async {
    if (state.busy) return false;
    emit(state.copyWith(mutatingItemId: itemId, clearError: true));

    try {
      final cart = await action();
      emit(
        state.copyWith(
          status: PurchaseCartStatus.ready,
          cart: cart,
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
          error: const AuthApiException(message: 'Unable to update the cart.'),
          clearMutating: true,
        ),
      );

      return false;
    }
  }
}
