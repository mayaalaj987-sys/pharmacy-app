import 'package:dio/dio.dart';

import '../../../core/network/error_handler.dart';
import '../domain/app_notification.dart';
import 'notifications_remote_data_source.dart';

class NotificationFeed {
  final int unreadCount;
  final List<AppNotification> notifications;

  const NotificationFeed({
    required this.unreadCount,
    required this.notifications,
  });

  static const empty = NotificationFeed(unreadCount: 0, notifications: []);
}

class NotificationsRepository {
  final NotificationsRemoteDataSource api;

  const NotificationsRepository(this.api);

  Future<NotificationFeed> fetchNotifications() async {
    try {
      return _parse((await api.getNotifications()).data);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// The employee's own bell, which works without a pharmacy.
  Future<NotificationFeed> fetchEmployeeNotifications() async {
    try {
      return _parse((await api.getEmployeeNotifications()).data);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> markEmployeeAsRead(int id) async {
    try {
      await api.markEmployeeAsRead(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  /// Both feeds return the same envelope, so they share one parser.
  NotificationFeed _parse(dynamic data) {
    if (data is! Map<String, dynamic>) return NotificationFeed.empty;
    final raw = data['notifications'];
    final list = raw is List
        ? raw
              .whereType<Map<String, dynamic>>()
              .map(AppNotification.fromJson)
              .toList(growable: false)
        : const <AppNotification>[];

    final rawUnread = data['unread_count'];
    return NotificationFeed(
      unreadCount: rawUnread is num
          ? rawUnread.toInt()
          : int.tryParse(rawUnread?.toString() ?? '') ??
                list.where((n) => !n.isRead).length,
      notifications: list,
    );
  }

  Future<void> markAsRead(int id) async {
    try {
      await api.markAsRead(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await api.markAllAsRead();
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> deleteNotification(int id) async {
    try {
      await api.deleteNotification(id);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }
}
