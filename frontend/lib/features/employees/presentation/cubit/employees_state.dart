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

  /// A pharmacy's staff work opposite shifts, so a shift is a seat and the
  /// shift list is the capacity. The backend enforces one person per shift
  /// with a unique index; this mirrors it so the UI can offer only free ones.
  static const shifts = <String>['morning', 'evening'];

  /// Shifts nobody covers yet, in a stable order.
  List<String> get freeShifts {
    final taken = current.map((employee) => employee.shift).toSet();
    return shifts.where((shift) => !taken.contains(shift)).toList();
  }

  bool get atCapacity => freeShifts.isEmpty;

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
