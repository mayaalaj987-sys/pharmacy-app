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
      final response = await api.getNotifications();
      final data = response.data;
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
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
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
}
