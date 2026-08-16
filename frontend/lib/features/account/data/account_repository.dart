import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/error_handler.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../auth/data/models/auth_session_model.dart';
import 'account_remote_data_source.dart';

class AccountRepository {
  final AccountRemoteDataSource api;
  final SessionStorage storage;
  final RegistrationStatusStorage registrationStatusStorage;

  const AccountRepository(
    this.api,
    this.storage,
    this.registrationStatusStorage,
  );

  Future<AuthSession> updateProfile({
    required String name,
    String? phone,
    XFile? profileImage,
  }) async {
    final data = FormData.fromMap({
      'name': name,
      'phone': phone ?? '',
      if (profileImage != null)
        'profile_image': await MultipartFile.fromFile(
          profileImage.path,
          filename: profileImage.name,
        ),
    });

    try {
      final response = await api.updateProfile(data);
      return await _session(response);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    try {
      await api.changePassword({
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': confirmation,
      });
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<AuthSession> updatePharmacy({
    required String name,
    required String address,
    bool includeCoordinates = false,
    double? latitude,
    double? longitude,
  }) async {
    try {
      final response = await api.updatePharmacy({
        'pharmacy_name': name,
        'pharmacy_address': address,
        if (includeCoordinates) 'latitude': latitude,
        if (includeCoordinates) 'longitude': longitude,
      });
      return await _session(response);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> deactivateAccount(String currentPassword) async {
    try {
      await api.deactivateAccount({'current_password': currentPassword});
      await storage.clearSession();
      await registrationStatusStorage.clearRegistrationStatusToken();
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<AuthSession> _session(Response<dynamic> response) async {
    final body = Map<String, dynamic>.from(response.data as Map);
    final data = Map<String, dynamic>.from(body['data'] as Map);
    final session = AuthSession.fromJson(
      Map<String, dynamic>.from(data['session'] as Map),
    );
    final active = session.activePharmacy;
    if (active != null) {
      await storage.saveActivePharmacyId(active.id);
    }
    return session;
  }
}
