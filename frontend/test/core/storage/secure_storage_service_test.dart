import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/storage/secure_storage_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
    'migrates a legacy SharedPreferences token into secure storage',
    () async {
      SharedPreferences.setMockInitialValues({'token': 'legacy-token'});
      final secureStore = FakeSecureValueStore();
      final storage = TokenStorage(secureStorage: secureStore);

      expect(await storage.getToken(), 'legacy-token');
      expect(secureStore.values['auth_token'], 'legacy-token');

      final preferences = await SharedPreferences.getInstance();
      expect(preferences.getString('token'), isNull);
    },
  );

  test('saves and clears token and active pharmacy together', () async {
    SharedPreferences.setMockInitialValues({});
    final secureStore = FakeSecureValueStore();
    final storage = TokenStorage(secureStorage: secureStore);

    await storage.saveToken('secure-token');
    await storage.saveActivePharmacyId(7);

    expect(await storage.getToken(), 'secure-token');
    expect(await storage.getActivePharmacyId(), 7);

    await storage.clearSession();

    expect(await storage.getToken(), isNull);
    expect(await storage.getActivePharmacyId(), isNull);
  });
}

class FakeSecureValueStore implements SecureValueStore {
  final Map<String, String> values = {};

  @override
  Future<void> delete(String key) async => values.remove(key);

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;
}
