import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employee_workspace_repository.dart';
import 'employee_workspace_state.dart';

class EmployeeWorkspaceCubit extends Cubit<EmployeeWorkspaceState> {
  final EmployeeWorkspaceRepository repository;

  EmployeeWorkspaceCubit(this.repository)
    : super(const EmployeeWorkspaceState.initial());

  Future<void> load(int employeeId) async {
    if (state.status == EmployeeWorkspaceStatus.loading) return;
    emit(
      state.copyWith(
        status: EmployeeWorkspaceStatus.loading,
        clearError: true,
      ),
    );
    try {
      final tasks = await repository.fetchMyTasks();
      final sales = await repository.fetchMySales(employeeId);
      emit(
        state.copyWith(
          status: EmployeeWorkspaceStatus.ready,
          tasks: tasks,
          sales: sales,
        ),
      );
    } on AuthApiException catch (error) {
      emit(
        state.copyWith(
          status: EmployeeWorkspaceStatus.failure,
          error: error,
        ),
      );
    } catch (_) {
      emit(
        state.copyWith(
          status: EmployeeWorkspaceStatus.failure,
          error: const AuthApiException(
            message: 'Unable to load your workspace.',
          ),
        ),
      );
    }
  }

  /// Updates the employee's own name/phone. Privileged fields are rejected
  /// server-side; this only ever sends name and phone.
  Future<bool> updateProfile({required String name, String? phone}) async {
    if (state.savingAccount) return false;
    emit(state.copyWith(savingAccount: true, clearError: true));
    try {
      await repository.updateProfile(name: name, phone: phone);
      emit(state.copyWith(savingAccount: false));
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(savingAccount: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          savingAccount: false,
          error: const AuthApiException(message: 'Unable to update profile.'),
        ),
      );
      return false;
    }
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    if (state.savingAccount) return false;
    emit(state.copyWith(savingAccount: true, clearError: true));
    try {
      await repository.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmation: confirmation,
      );
      emit(state.copyWith(savingAccount: false));
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(savingAccount: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          savingAccount: false,
          error: const AuthApiException(message: 'Unable to change password.'),
        ),
      );
      return false;
    }
  }

  /// Marks a task done, then refetches authoritative state. Nothing is changed
  /// locally when the API rejects the request.
  Future<bool> markTaskDone(int employeeId, int taskId) async {
    if (state.mutatingTaskId != null) return false;
    emit(state.copyWith(mutatingTaskId: taskId, clearError: true));
    try {
      await repository.markTaskDone(taskId);
      final tasks = await repository.fetchMyTasks();
      emit(
        state.copyWith(
          status: EmployeeWorkspaceStatus.ready,
          tasks: tasks,
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
            message: 'Unable to update the task.',
          ),
          clearMutating: true,
        ),
      );
      return false;
    }
  }
}
