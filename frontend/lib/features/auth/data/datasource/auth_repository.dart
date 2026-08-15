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
  }
  Future<int> registerPharmacist(FormData data) async {
    final response = await api.registerPharmacist(data);
    print("FULL RESPONSE: ${response.data}");

    // لو الباك رجع pharmacist_id مباشرة
    if (response.data["pharmacist_id"] != null) {
      return int.parse(response.data["pharmacist_id"].toString());
    }

    // لو الباك رجع data كـ object
    final dataField = response.data["data"];
    if (dataField is Map) {
      return int.parse(dataField["id"].toString());
    }

    // لو data هو int مباشرة
    if (dataField is int) {
      return dataField;
    }

    throw Exception("pharmacist_id not found in response");
  }
  Future<void> registerPharmacy(FormData data) async {
    await api.registerPharmacy(data);
  }
}
