import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/tasks_repository.dart';

enum TasksStatus { initial, loading, ready, failure }

class TasksState {
  final TasksStatus status;
  final PharmacyTasks tasks;
  final AuthApiException? error;

  /// True while a task is being created.
  final bool creating;

  /// Id of the task currently being deleted.
  final int? deletingTaskId;

  const TasksState({
    this.status = TasksStatus.initial,
    this.tasks = PharmacyTasks.empty,
    this.error,
    this.creating = false,
    this.deletingTaskId,
  });

  const TasksState.initial() : this();

  bool get busy => creating || deletingTaskId != null;

  TasksState copyWith({
    TasksStatus? status,
    PharmacyTasks? tasks,
    AuthApiException? error,
    bool? creating,
    int? deletingTaskId,
    bool clearError = false,
    bool clearDeleting = false,
  }) {
    return TasksState(
      status: status ?? this.status,
      tasks: tasks ?? this.tasks,
      error: clearError ? null : (error ?? this.error),
      creating: creating ?? this.creating,
      deletingTaskId:
          clearDeleting ? null : (deletingTaskId ?? this.deletingTaskId),
    );
  }
}
