import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/notifications/data/notifications_remote_data_source.dart';
import 'package:phamacy_managment/features/notifications/data/notifications_repository.dart';
import 'package:phamacy_managment/features/notifications/domain/app_notification.dart';
import 'package:phamacy_managment/features/notifications/presentation/cubit/notifications_cubit.dart';
import 'package:phamacy_managment/features/notifications/presentation/cubit/notifications_state.dart';

void main() {
  group('parsing', () {
    test('parses the envelope, unread count and read flags', () async {
      final feed =
          await NotificationsRepository(FakeNotificationsApi()).fetchNotifications();

      expect(feed.unreadCount, 2);
      expect(feed.notifications, hasLength(3));
      expect(feed.notifications.first.id, 1);
      expect(feed.notifications.first.isRead, isFalse);
      expect(feed.notifications.last.isRead, isTrue);
      expect(feed.notifications.first.date?.year, 2026);
    });

    test('accepts integer 1/0 for is_read', () {
      expect(
        AppNotification.fromJson({'id': 1, 'is_read': 1, 'type': 'sale'}).isRead,
        isTrue,
      );
      expect(
        AppNotification.fromJson({'id': 2, 'is_read': 0, 'type': 'sale'}).isRead,
        isFalse,
      );
    });
  });

  group('English display mapping', () {
    AppNotification build(String type, {String title = '', String message = ''}) =>
        AppNotification(
          id: 1,
          title: title,
          message: message,
          type: type,
          isRead: false,
        );

    test('Arabic backend text is never shown; type drives English copy', () {
      final n = build(
        'order',
        title: 'طلب جديد',
        message: 'تم إنشاء طلب جديد من المورد',
      );

      expect(n.displayTitle, 'Purchase order update');
      expect(n.displayMessage, 'A purchase order status has changed.');
      expect(_hasNonAscii(n.displayTitle), isFalse);
      expect(_hasNonAscii(n.displayMessage), isFalse);
    });

    test('distinct events map to distinct English copy', () {
      final titles = <String>{
        for (final t in [
          'pharmacy_approved',
          'pharmacy_rejected',
          'employee_approved',
          'order',
          'sale',
          'task',
          'low_stock',
          'out_of_stock',
        ])
          build(t, title: 'عنوان عربي').displayTitle,
      };

      // No two distinguishable event types collapse onto the same label.
      expect(titles, hasLength(8));
    });

    test('English backend text is preserved as-is', () {
      final n = build(
        'pharmacy_approved',
        title: 'Pharmacy approved',
        message: 'Your pharmacy registration has been approved.',
      );

      expect(n.displayTitle, 'Pharmacy approved');
      expect(n.displayMessage, 'Your pharmacy registration has been approved.');
    });

    test('unknown type with Arabic text falls back to a safe English default', () {
      final n = build('something_new', title: 'غير معروف', message: 'رسالة');

      expect(n.displayTitle, 'Notification');
      expect(n.displayMessage, 'You have a new notification.');
    });
  });

  group('cubit', () {
    test('load exposes ready state with the unread count', () async {
      final cubit =
          NotificationsCubit(NotificationsRepository(FakeNotificationsApi()));

      await cubit.load();

      expect(cubit.state.status, NotificationsStatus.ready);
      expect(cubit.state.unreadCount, 2);
      expect(cubit.state.badgeLabel, '2');
      await cubit.close();
    });

    test('badge caps at 99+ and is zero when nothing is unread', () async {
      final api = FakeNotificationsApi()..unreadCount = 150;
      final cubit = NotificationsCubit(NotificationsRepository(api));
      await cubit.load();
      expect(cubit.state.badgeLabel, '99+');
      await cubit.close();

      final empty = FakeNotificationsApi()..unreadCount = 0;
      final cubit2 = NotificationsCubit(NotificationsRepository(empty));
      await cubit2.load();
      expect(cubit2.state.unreadCount, 0);
      await cubit2.close();
    });

    test('marking read refetches and decrements the unread count', () async {
      final api = FakeNotificationsApi();
      final cubit = NotificationsCubit(NotificationsRepository(api));
      await cubit.load();
      expect(cubit.state.unreadCount, 2);

      final ok = await cubit.markAsRead(1);

      expect(ok, isTrue);
      expect(api.readIds, [1]);
      expect(cubit.state.unreadCount, 1);
      expect(cubit.state.mutatingId, isNull);
      await cubit.close();
    });

    test('mark all as read clears the count', () async {
      final api = FakeNotificationsApi();
      final cubit = NotificationsCubit(NotificationsRepository(api));
      await cubit.load();

      final ok = await cubit.markAllAsRead();

      expect(ok, isTrue);
      expect(api.markAllCalls, 1);
      expect(cubit.state.unreadCount, 0);
      expect(cubit.state.markingAll, isFalse);
      await cubit.close();
    });

    test('a rejected mark-all (employee 401) surfaces an error', () async {
      final api = FakeNotificationsApi()..failMarkAll = true;
      final cubit = NotificationsCubit(NotificationsRepository(api));
      await cubit.load();

      final ok = await cubit.markAllAsRead();

      expect(ok, isFalse);
      expect(cubit.state.error, isNotNull);
      expect(cubit.state.markingAll, isFalse);
      await cubit.close();
    });

    test('a load failure surfaces an error state', () async {
      final cubit = NotificationsCubit(
        NotificationsRepository(FakeNotificationsApi()..failLoad = true),
      );

      await cubit.load();

      expect(cubit.state.status, NotificationsStatus.failure);
      expect(cubit.state.error, isNotNull);
      await cubit.close();
    });
  });
}

bool _hasNonAscii(String v) => v.runes.any((r) => r > 127);

class FakeNotificationsApi implements NotificationsRemoteDataSource {
  final List<int> readIds = [];

  /// Records that the employee bell used its own endpoint rather than the
  /// pharmacy-scoped one, which is gated on an active pharmacy.
  int employeeFeedCalls = 0;
  final List<int> employeeReadIds = [];
  int markAllCalls = 0;
  int unreadCount = 2;
  bool failLoad = false;
  bool failMarkAll = false;
  bool _allRead = false;

  DioException _error(String path, int status) => DioException(
    requestOptions: RequestOptions(path: path),
    response: Response<dynamic>(
      requestOptions: RequestOptions(path: path),
      statusCode: status,
      data: {'message': 'Unauthenticated.', 'code': 'unauthenticated'},
    ),
    type: DioExceptionType.badResponse,
  );

  @override
  Future<Response<dynamic>> getNotifications() async {
    if (failLoad) throw _error('/notifications', 500);
    final effectiveUnread = _allRead
        ? 0
        : (unreadCount - readIds.length).clamp(0, 1 << 30);

    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/notifications'),
      data: {
        'unread_count': effectiveUnread,
        'notifications': [
          {
            'id': 1,
            'title': 'طلب جديد',
            'message': 'تم إنشاء طلب جديد',
            'type': 'order',
            'is_read': _allRead || readIds.contains(1),
            'date': '2026-08-17',
            'created_at': '2026-08-17T09:00:00.000Z',
          },
          {
            'id': 2,
            'title': 'Pharmacy approved',
            'message': 'Your pharmacy registration has been approved.',
            'type': 'pharmacy_approved',
            'is_read': _allRead || readIds.contains(2),
            'date': '2026-08-17',
          },
          {
            'id': 3,
            'title': 'عملية بيع جديدة',
            'message': 'تمت عملية بيع',
            'type': 'sale',
            'is_read': true,
            'date': '2026-08-16',
          },
        ],
      },
    );
  }

  @override
  Future<Response<dynamic>> markAsRead(int id) async {
    readIds.add(id);
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/notifications/$id/read'),
      data: {'message': 'ok'},
    );
  }

  @override
  Future<Response<dynamic>> markAllAsRead() async {
    if (failMarkAll) throw _error('/notifications/read-all', 401);
    markAllCalls++;
    _allRead = true;
    return Response<dynamic>(
      requestOptions: RequestOptions(path: '/notifications/read-all'),
      data: {'message': 'ok'},
    );
  }

  @override
  Future<Response<dynamic>> getEmployeeNotifications() async {
    employeeFeedCalls++;
    return getNotifications();
  }

  @override
  Future<Response<dynamic>> markEmployeeAsRead(int id) async {
    employeeReadIds.add(id);
    return markAsRead(id);
  }
}
