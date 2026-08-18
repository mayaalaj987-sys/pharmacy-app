import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employees_repository.dart';
import 'employees_state.dart';

class EmployeesCubit extends Cubit<EmployeesState> {
  final EmployeesRepository repository;

  EmployeesCubit(this.repository) : super(const EmployeesState.initial());

  Future<void> load(int pharmacyId) async {
    if (state.status == EmployeesStatus.loading) return;
    emit(state.copyWith(status: EmployeesStatus.loading, clearError: true));
    try {
      final current = await repository.fetchPharmacyEmployees(pharmacyId);
      final pending = await repository.fetchPendingEmployees();
      emit(
        state.copyWith(
          status: EmployeesStatus.ready,
          current: current,
          pending: pending,
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: EmployeesStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: EmployeesStatus.failure,
          error: const AuthApiException(message: 'Unable to load employees.'),
        ),
      );
    }
  }

  Future<bool> sendOffer(
    int pharmacyId,
    int employeeId, {
    required String shift,
    double? salary,
  }) {
    return _mutate(
      pharmacyId,
      employeeId,
      () => repository.sendOffer(employeeId, shift: shift, salary: salary),
    );
  }

  Future<bool> promote(int pharmacyId, int employeeId) {
    return _mutate(
      pharmacyId,
      employeeId,
      () => repository.promoteEmployee(employeeId),
    );
  }

  Future<bool> dismiss(int pharmacyId, int employeeId) {
    return _mutate(
      pharmacyId,
      employeeId,
      () => repository.dismissEmployee(employeeId),
    );
  }

  /// Runs a write then refetches authoritative server state. Nothing is
  /// removed or added locally when the API rejects the operation.
  Future<bool> _mutate(
    int pharmacyId,
    int employeeId,
    Future<void> Function() action,
  ) async {
    if (state.mutatingEmployeeId != null) return false;
    emit(state.copyWith(mutatingEmployeeId: employeeId, clearError: true));
    try {
      await action();
      final current = await repository.fetchPharmacyEmployees(pharmacyId);
      final pending = await repository.fetchPendingEmployees();
      emit(
        state.copyWith(
          status: EmployeesStatus.ready,
          current: current,
          pending: pending,
          clearMutating: true,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      // Includes the 400 capacity limit and the 409 document-retention refusal.
      emit(state.copyWith(error: error, clearMutating: true));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(
            message: 'Unable to update the employee.',
          ),
          clearMutating: true,
        ),
      );
      return false;
    }
  }
}
