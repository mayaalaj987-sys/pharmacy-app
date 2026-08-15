import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract class SessionStorage {
  Future<void> saveToken(String token);
  Future<String?> getToken();
  Future<void> saveActivePharmacyId(int pharmacyId);
  Future<int?> getActivePharmacyId();
  Future<void> clearActivePharmacy();
  Future<void> clearToken();
  Future<void> clearSession();
}

abstract class SecureValueStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class FlutterSecureValueStore implements SecureValueStore {
  final FlutterSecureStorage _storage;

  const FlutterSecureValueStore({
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

class TokenStorage implements SessionStorage {
  static const _tokenKey = 'auth_token';
  static const _activePharmacyKey = 'active_pharmacy_id';
  static const _legacyTokenKey = 'token';

  final SecureValueStore _secureStorage;

  TokenStorage({SecureValueStore? secureStorage})
    : _secureStorage = secureStorage ?? const FlutterSecureValueStore();

  @override
  Future<void> saveToken(String token) {
    return _secureStorage.write(_tokenKey, token);
  }

  @override
  Future<String?> getToken() async {
    final secureToken = await _secureStorage.read(_tokenKey);
    if (secureToken != null && secureToken.isNotEmpty) {
      return secureToken;
    }

    final preferences = await SharedPreferences.getInstance();
    final legacyToken = preferences.getString(_legacyTokenKey);
    if (legacyToken == null || legacyToken.isEmpty) {
      return null;
    }

    await saveToken(legacyToken);
    await preferences.remove(_legacyTokenKey);
    return legacyToken;
  }

  @override
  Future<void> saveActivePharmacyId(int pharmacyId) {
    return _secureStorage.write(_activePharmacyKey, pharmacyId.toString());
  }

  @override
  Future<int?> getActivePharmacyId() async {
    final value = await _secureStorage.read(_activePharmacyKey);
    return int.tryParse(value ?? '');
  }

  @override
  Future<void> clearActivePharmacy() {
    return _secureStorage.delete(_activePharmacyKey);
  }

  @override
  Future<void> clearToken() {
    return _secureStorage.delete(_tokenKey);
  }

  @override
  Future<void> clearSession() async {
    await Future.wait([clearToken(), clearActivePharmacy()]);
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(_legacyTokenKey);
  }
}
