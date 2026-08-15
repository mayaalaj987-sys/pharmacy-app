import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/settings_page/settings_about_tile.dart';
import '../../../../core/widgets/settings_page/settings_delete_account_tile.dart';
import '../../../../core/widgets/settings_page/settings_logout_tile.dart';
import '../../../../core/widgets/settings_page/settings_profile_card.dart';
import '../../../../core/widgets/settings_page/settings_rate_tile.dart';
import '../../../../core/widgets/settings_page/settings_theme_tile.dart';

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Settings"),
      ),

      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),

        child: Column(
          children: const [
            SettingsProfileCard(),

            SizedBox(height: 16),

            SettingsThemeTile(),

            SizedBox(height: 16),

            SettingsRateTile(),

            SizedBox(height: 16),

            SettingsAboutTile(),

            SizedBox(height: 16),

            SettingsLogoutTile(),

            SizedBox(height: 16),

            SettingsDeleteAccountTile(),
          ],
        ),
      ),
    );
  }
}
