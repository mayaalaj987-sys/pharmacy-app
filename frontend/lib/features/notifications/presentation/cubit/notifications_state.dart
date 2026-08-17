import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/notifications_repository.dart';

enum NotificationsStatus { initial, loading, ready, failure }

class NotificationsState {
  final NotificationsStatus status;
  final NotificationFeed feed;
  final AuthApiException? error;

  /// Id of the notification currently being marked read.
  final int? mutatingId;

  /// True while "mark all as read" is in flight.
  final bool markingAll;

  const NotificationsState({
    this.status = NotificationsStatus.initial,
    this.feed = NotificationFeed.empty,
    this.error,
    this.mutatingId,
    this.markingAll = false,
  });

  const NotificationsState.initial() : this();

  int get unreadCount => feed.unreadCount;

  /// Badge label: hidden at 0 (handled by the widget), capped at "99+".
  String get badgeLabel => unreadCount > 99 ? '99+' : unreadCount.toString();

  bool get busy => mutatingId != null || markingAll;

  NotificationsState copyWith({
    NotificationsStatus? status,
    NotificationFeed? feed,
    AuthApiException? error,
    int? mutatingId,
    bool? markingAll,
    bool clearError = false,
    bool clearMutating = false,
  }) {
    return NotificationsState(
      status: status ?? this.status,
      feed: feed ?? this.feed,
      error: clearError ? null : (error ?? this.error),
      mutatingId: clearMutating ? null : (mutatingId ?? this.mutatingId),
      markingAll: markingAll ?? this.markingAll,
    );
  }
}
