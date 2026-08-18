import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employee_offers_repository.dart';
import 'employee_offers_state.dart';

class EmployeeOffersCubit extends Cubit<EmployeeOffersState> {
  final EmployeeOffersRepository repository;

  /// Reloads the auth session. Accepting changes what the whole app is, and
  /// AuthGate swaps the unattached shell for the working one off the back of
  /// that reload — so it cannot be patched locally.
  ///
  /// A callback rather than the AuthCubit itself: this feature needs exactly
  /// one thing from auth, and saying so keeps the dependency one function wide.
  final Future<void> Function() reloadSession;

  EmployeeOffersCubit(this.repository, this.reloadSession)
    : super(const EmployeeOffersState.initial());

  Future<void> load() async {
    if (state.status == EmployeeOffersStatus.loading) return;
    emit(state.copyWith(status: EmployeeOffersStatus.loading, clearError: true));

    try {
      emit(
        state.copyWith(
          status: EmployeeOffersStatus.ready,
          inbox: await repository.fetchInbox(),
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: EmployeeOffersStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: EmployeeOffersStatus.failure,
          error: const AuthApiException(message: 'Unable to load your offers.'),
        ),
      );
    }
  }

  /// Takes an offer. Returns false and leaves the list untouched on refusal.
  Future<bool> accept(int offerId) async {
    if (state.accepting) return false;
    emit(state.copyWith(acceptingOfferId: offerId, clearError: true));

    try {
      await repository.acceptOffer(offerId);
      // Refetch before the session reload: the list is authoritative about
      // which offers are still live, and it is what stays on screen if routing
      // to the working shell takes a moment.
      emit(
        state.copyWith(
          status: EmployeeOffersStatus.ready,
          inbox: await repository.fetchInbox(),
          clearAccepting: true,
        ),
      );
      await reloadSession();
      return true;
    } on AuthApiException catch (error) {
      // A refusal is nearly always the world having moved on — the shift was
      // taken, the pharmacy was suspended — so the list is refreshed to show
      // why rather than leaving a stale button.
      emit(state.copyWith(error: error, clearAccepting: true));
      await _refreshQuietly();
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(
            message: 'Unable to accept this offer.',
          ),
          clearAccepting: true,
        ),
      );
      return false;
    }
  }

  Future<void> _refreshQuietly() async {
    try {
      emit(state.copyWith(inbox: await repository.fetchInbox()));
    } catch (_) {
      // Best effort: the error already shown is the useful one.
    }
  }
}
