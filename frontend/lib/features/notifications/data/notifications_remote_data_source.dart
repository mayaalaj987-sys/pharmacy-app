import 'package:dio/dio.dart';

abstract class NotificationsRemoteDataSource {
  Future<Response<dynamic>> getNotifications();

  Future<Response<dynamic>> markAsRead(int id);

  /// Pharmacist-only on the backend.
  Future<Response<dynamic>> markAllAsRead();
}
