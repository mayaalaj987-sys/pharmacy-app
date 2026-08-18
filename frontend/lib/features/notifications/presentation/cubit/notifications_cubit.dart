import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/notifications_repository.dart';
import 'notifications_state.dart';

class NotificationsCubit extends Cubit<NotificationsState> {
  final NotificationsRepository repository;

  NotificationsCubit(this.repository) : super(const NotificationsState.initial());

  /// Fetches the feed. Called on app/screen open and after mutations.
  /// No polling: the backend has no push channel and polling is not warranted.
  Future<void> load() async {
    if (state.status == NotificationsStatus.loading) return;
    emit(state.copyWith(status: NotificationsStatus.loading, clearError: true));
    try {
      final feed = await repository.fetchNotifications();
      emit(state.copyWith(status: NotificationsStatus.ready, feed: feed));
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: NotificationsStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: NotificationsStatus.failure,
          error: const AuthApiException(
            message: 'Unable to load notifications.',
          ),
        ),
      );
    }
  }

  /// The employee's own bell. Separate entry point rather than a flag on
  /// [load], because which endpoint to call is decided by the shell that is
  /// showing — an unattached employee has no pharmacy for the other one.
  Future<void> loadForEmployee() async {
    if (state.status == NotificationsStatus.loading) return;
    emit(state.copyWith(status: NotificationsStatus.loading, clearError: true));
    try {
      final feed = await repository.fetchEmployeeNotifications();
      emit(state.copyWith(status: NotificationsStatus.ready, feed: feed));
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: NotificationsStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: NotificationsStatus.failure,
          error: const AuthApiException(
            message: 'Unable to load notifications.',
          ),
        ),
      );
    }
  }

  /// Silent refresh used by the badge; never flips the screen into a
  /// loading/error state.
  Future<void> refreshQuietly() async {
    try {
      final feed = await repository.fetchNotifications();
      emit(state.copyWith(status: NotificationsStatus.ready, feed: feed));
    } catch (_) {
      // Badge refresh is best-effort.
    }
  }

  Future<bool> markAsRead(int id) async {
    if (state.busy) return false;
    emit(state.copyWith(mutatingId: id, clearError: true));
    try {
      await repository.markAsRead(id);
      final feed = await repository.fetchNotifications();
      emit(
        state.copyWith(
          status: NotificationsStatus.ready,
          feed: feed,
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
            message: 'Unable to update the notification.',
          ),
          clearMutating: true,
        ),
      );
      return false;
    }
  }

  /// Pharmacist-only on the backend; employees receive 401 and the UI hides it.
  Future<bool> markAllAsRead() async {
    if (state.busy) return false;
    emit(state.copyWith(markingAll: true, clearError: true));
    try {
      await repository.markAllAsRead();
      final feed = await repository.fetchNotifications();
      emit(
        state.copyWith(
          status: NotificationsStatus.ready,
          feed: feed,
          markingAll: false,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(error: error, markingAll: false));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          error: const AuthApiException(
            message: 'Unable to update the notifications.',
          ),
          markingAll: false,
        ),
      );
      return false;
    }
  }
}
