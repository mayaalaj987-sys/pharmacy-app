import 'package:dio/dio.dart';

import '../../../core/network/api_constants.dart';
import '../../../core/network/dio_client.dart';
import 'notifications_remote_data_source.dart';

class NotificationsApi implements NotificationsRemoteDataSource {
  final Dio dio;

  NotificationsApi({Dio? dio}) : dio = dio ?? DioClient.dio;

  @override
  Future<Response<dynamic>> getNotifications() {
    return dio.get(ApiConstants.notifications);
  }

  @override
  Future<Response<dynamic>> markAsRead(int id) {
    return dio.post('${ApiConstants.notifications}/$id/read');
  }

  @override
  Future<Response<dynamic>> markAllAsRead() {
    return dio.post(ApiConstants.notificationsReadAll);
  }

  @override
  Future<Response<dynamic>> getEmployeeNotifications() {
    return dio.get(
      ApiConstants.employeeNotifications,
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }

  @override
  Future<Response<dynamic>> markEmployeeAsRead(int id) {
    return dio.post(
      '${ApiConstants.employeeNotifications}/$id/read',
      options: Options(extra: {'skipActivePharmacy': true}),
    );
  }
}
