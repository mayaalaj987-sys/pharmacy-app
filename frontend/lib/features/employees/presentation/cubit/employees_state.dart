import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/employee.dart';

enum EmployeesStatus { initial, loading, ready, failure }

class EmployeesState {
  final EmployeesStatus status;

  /// Approved employees of the active pharmacy.
  final List<Employee> current;

  /// Global pool of pending employment requests.
  final List<Employee> pending;

  final AuthApiException? error;

  /// Id of the employee currently being approved/dismissed.
  final int? mutatingEmployeeId;

  const EmployeesState({
    this.status = EmployeesStatus.initial,
    this.current = const <Employee>[],
    this.pending = const <Employee>[],
    this.error,
    this.mutatingEmployeeId,
  });

  const EmployeesState.initial() : this();

  /// The backend caps approved employees per pharmacy at two.
  static const maxApprovedEmployees = 2;

  bool get atCapacity => current.length >= maxApprovedEmployees;

  EmployeesState copyWith({
    EmployeesStatus? status,
    List<Employee>? current,
    List<Employee>? pending,
    AuthApiException? error,
    int? mutatingEmployeeId,
    bool clearError = false,
    bool clearMutating = false,
  }) {
    return EmployeesState(
      status: status ?? this.status,
      current: current ?? this.current,
      pending: pending ?? this.pending,
      error: clearError ? null : (error ?? this.error),
      mutatingEmployeeId: clearMutating
          ? null
          : (mutatingEmployeeId ?? this.mutatingEmployeeId),
    );
  }
}
