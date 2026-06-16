import 'package:dio/dio.dart';

import '../../../../core/storage/secure_storage_service.dart';
import '../../../../core/globals.dart';
import '../models/user_model.dart';
import 'auth_api.dart';

class AuthRepository {
  final AuthApi api;
  final TokenStorage storage;

  AuthRepository(this.api, this.storage);

  Future<void> login(String email, String password) async {
    final response = await api.login(email, password);

    final token = response.data["token"];

    if (token != null) {
      await storage.saveToken(token);
    }

    final pharmacistJson = response.data["pharmacist"];
    if (pharmacistJson != null) {
      currentUser = UserModel.fromJson(pharmacistJson);
    }
  }

  Future<int> registerPharmacist(FormData data) async {
    final response = await api.registerPharmacist(data);
    return response.data["pharmacist_id"] as int;
  }

  Future<void> registerPharmacy(FormData data) async {
    await api.registerPharmacy(data);
  }
}