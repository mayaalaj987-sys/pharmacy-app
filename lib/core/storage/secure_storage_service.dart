// مشان تخزين وحفظ ال token
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {

  static const _storage = FlutterSecureStorage();

  static Future<void> saveToken(String token) async {
    await _storage.write(
      key: 'token',
      value: token,
    );
  }

  static Future<String?> getToken() async {
    return await _storage.read(key: 'token');
  }
}