import 'package:flutter/material.dart';

import 'package:provider/provider.dart';

import '../../theme/theme_provider.dart';

class SettingsThemeTile extends StatelessWidget {
  const SettingsThemeTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ThemeProvider>(
      builder: (context, themeProvider, child) {
        return Card(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),

          child: SwitchListTile(
            title: const Text("Dark Mode"),

            subtitle: const Text("Enable dark appearance"),

            value: themeProvider.isDark,

            onChanged: (value) {
              themeProvider.toggleTheme(value);
            },
          ),
        );
      },
    );
  }
}
