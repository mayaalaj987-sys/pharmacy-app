import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';
import 'package:provider/provider.dart';

import '../../theme/theme_provider.dart';

class SettingsThemeTile extends StatelessWidget {
  const SettingsThemeTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ThemeProvider>(
      builder: (context, themeProvider, child) {
        return Card(
          color: AppColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),

          child: SwitchListTile(
            // لون الطابة لما يكون ON
            activeThumbColor: AppColors.tealGreen,

            // لون الطابة لما يكون OFF (قبل الكبس)
            inactiveThumbColor: AppColors.veryLightGreen,

            // لون الخلفية لما يكون OFF
            inactiveTrackColor: AppColors.tealGreen,

            // لون الخلفية لما يكون ON
            activeTrackColor: AppColors.veryLightGreen,

            // لون الإطار حسب حالة السويتش
            trackOutlineColor: WidgetStateProperty.resolveWith((states) {
              if (states.contains(WidgetState.selected)) {
                return AppColors.tealGreen;
              }
              return AppColors.tealGreen;
            }),

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
