import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ThemeProvider extends ChangeNotifier {
  static const _preferenceKey = 'app_theme_mode';

  ThemeProvider([this._themeMode = ThemeMode.system]);

  ThemeMode _themeMode;

  ThemeMode get themeMode => _themeMode;

  bool get isDark => _themeMode == ThemeMode.dark;

  static Future<ThemeProvider> load() async {
    final preferences = await SharedPreferences.getInstance();
    final savedMode = preferences.getString(_preferenceKey);
    final mode = ThemeMode.values.firstWhere(
      (value) => value.name == savedMode,
      orElse: () => ThemeMode.system,
    );
    return ThemeProvider(mode);
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    if (_themeMode == mode) return;
    _themeMode = mode;
    notifyListeners();

    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(_preferenceKey, mode.name);
  }

  void toggleTheme(bool value) {
    setThemeMode(value ? ThemeMode.dark : ThemeMode.light);
  }
}
