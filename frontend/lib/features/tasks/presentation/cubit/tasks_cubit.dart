import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/tasks_repository.dart';
import 'tasks_state.dart';

class TasksCubit extends Cubit<TasksState> {
  final TasksRepository repository;

  TasksCubit(this.repository) : super(const TasksState.initial());

  Future<void> load() async {
    if (state.status == TasksStatus.loading) return;
    emit(state.copyWith(status: TasksStatus.loading, clearError: true));
    try {
      final tasks = await repository.fetchPharmacyTasks();
      emit(state.copyWith(status: TasksStatus.ready, tasks: tasks));
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: TasksStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: TasksStatus.failure,
          error: const AuthApiException(message: 'Unable to load tasks.'),
        ),
      );
    }
  }

  Future<bool> createTask({
    required int employeeId,
    required String title,
    String? description,
  }) async {
    if (state.busy) return false;
    emit(state.copyWith(creating: true, clearError: true));
    try {
      await repository.createTask(
        employeeId: employeeId,
        title: title,
        description: description,
      );
      final tasks = await repository.fetchPharmacyTasks();
      emit(
        state.copyWith(
          status: TasksStatus.ready,
          tasks: tasks,
          creating: false,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(creating: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          creating: false,
          error: const AuthApiException(message: 'Unable to create the task.'),
        ),
      );
      return false;
    }
  }

  /// Deletes a task then refetches authoritative state. Nothing is removed
  /// locally when the API rejects the request.
  Future<bool> deleteTask(int id) async {
    if (state.busy) return false;
    emit(state.copyWith(deletingTaskId: id, clearError: true));
    try {
      await repository.deleteTask(id);
      final tasks = await repository.fetchPharmacyTasks();
      emit(
        state.copyWith(
          status: TasksStatus.ready,
          tasks: tasks,
          clearDeleting: true,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(error: error, clearDeleting: true));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(message: 'Unable to delete the task.'),
          clearDeleting: true,
        ),
      );
      return false;
    }
  }
}
