import 'package:flutter/material.dart';

class ThemeProvider extends ChangeNotifier {
  bool isDark = false;

  void toggleTheme(bool value) {
    isDark = value;
    notifyListeners();
  }
}
