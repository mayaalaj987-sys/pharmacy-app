import 'package:dio/dio.dart';

abstract class NotificationsRemoteDataSource {
  Future<Response<dynamic>> getNotifications();

  Future<Response<dynamic>> markAsRead(int id);

  /// Pharmacist-only on the backend.
  Future<Response<dynamic>> markAllAsRead();

  Future<Response<dynamic>> deleteNotification(int id);

  /// The employee's own bell. A separate endpoint because the pharmacy-scoped
  /// one sits behind the active-pharmacy gate, which someone waiting on a job
  /// does not have. It returns their personal messages merged with their
  /// pharmacy's, once they have one.
  Future<Response<dynamic>> getEmployeeNotifications();

  Future<Response<dynamic>> markEmployeeAsRead(int id);
}
