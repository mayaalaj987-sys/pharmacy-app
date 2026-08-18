import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employee_offers_repository.dart';
import 'employee_offers_state.dart';

class EmployeeOffersCubit extends Cubit<EmployeeOffersState> {
  final EmployeeOffersRepository repository;

  EmployeeOffersCubit(this.repository) : super(const EmployeeOffersState.initial());

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
}
