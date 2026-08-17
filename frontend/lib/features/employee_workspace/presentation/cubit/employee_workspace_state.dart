import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employee_workspace_repository.dart';

enum EmployeeWorkspaceStatus { initial, loading, ready, failure }

class EmployeeWorkspaceState {
  final EmployeeWorkspaceStatus status;
  final MyTasks tasks;
  final MySales sales;
  final AuthApiException? error;

  /// Id of the task currently being marked done.
  final int? mutatingTaskId;

  /// True while a profile or password update is in flight.
  final bool savingAccount;

  const EmployeeWorkspaceState({
    this.status = EmployeeWorkspaceStatus.initial,
    this.tasks = MyTasks.empty,
    this.sales = MySales.empty,
    this.error,
    this.mutatingTaskId,
    this.savingAccount = false,
  });

  const EmployeeWorkspaceState.initial() : this();

  EmployeeWorkspaceState copyWith({
    EmployeeWorkspaceStatus? status,
    MyTasks? tasks,
    MySales? sales,
    AuthApiException? error,
    int? mutatingTaskId,
    bool? savingAccount,
    bool clearError = false,
    bool clearMutating = false,
  }) {
    return EmployeeWorkspaceState(
      status: status ?? this.status,
      tasks: tasks ?? this.tasks,
      sales: sales ?? this.sales,
      error: clearError ? null : (error ?? this.error),
      mutatingTaskId:
          clearMutating ? null : (mutatingTaskId ?? this.mutatingTaskId),
      savingAccount: savingAccount ?? this.savingAccount,
    );
  }
}
