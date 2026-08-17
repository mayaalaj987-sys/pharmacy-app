import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/settings_page/settings_about_tile.dart';
import '../../../support/presentation/pages/support_page.dart';
import '../../../../core/widgets/settings_page/settings_active_pharmacy_tile.dart';
import '../../../../core/widgets/settings_page/settings_add_pharmacy_tile.dart';
import '../../../../core/widgets/settings_page/settings_deactivate_account_tile.dart';
import '../../../../core/widgets/settings_page/settings_logout_tile.dart';
import '../../../../core/widgets/settings_page/settings_pharmacy_card.dart';
import '../../../../core/widgets/settings_page/settings_profile_card.dart';
import '../../../../core/widgets/settings_page/settings_rate_tile.dart';
import '../../../../core/widgets/settings_page/settings_theme_tile.dart';
import 'settings_change_password_page.dart';
import 'settings_edit_profile_page.dart';

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
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _SectionTitle('PROFILE'),
            const SettingsProfileCard(),
            const SizedBox(height: 12),
            const SettingsActivePharmacyTile(),
            const SizedBox(height: 22),
            const _SectionTitle('PHARMACY'),
            const SettingsPharmacyCard(),
            const SizedBox(height: 12),
            const SettingsAddPharmacyTile(),
            const SizedBox(height: 22),
            const _SectionTitle('ACCOUNT'),
            Card(
              color: AppColors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              child: ListTile(
                leading: const Icon(
                  Icons.manage_accounts_outlined,
                  color: AppColors.tealGreen,
                ),
                title: const Text('Edit Profile'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () async {
                  final updated = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const SettingsEditProfilePage(),
                    ),
                  );
                  if (updated == true && context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Profile updated successfully.'),
                      ),
                    );
                  }
                },
              ),
            ),
            const SizedBox(height: 12),
            Card(
              color: AppColors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              child: ListTile(
                leading: const Icon(Icons.password, color: AppColors.tealGreen),
                title: const Text('Change Password'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () async {
                  final changed = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const SettingsChangePasswordPage(),
                    ),
                  );
                  if (changed == true && context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Password changed successfully.'),
                      ),
                    );
                  }
                },
              ),
            ),
            const SizedBox(height: 12),
            const SettingsLogoutTile(),
            const SizedBox(height: 12),
            const SettingsDeactivateAccountTile(),
            const SizedBox(height: 22),
            const _SectionTitle('GENERAL'),
            const SettingsThemeTile(),
            const SizedBox(height: 12),
            const SettingsRateTile(),
            const SizedBox(height: 12),
            Card(
              color: AppColors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              child: ListTile(
                key: const ValueKey('settings-support-tile'),
                leading: const Icon(
                  Icons.support_agent,
                  color: AppColors.tealGreen,
                ),
                title: const Text('Contact Support'),
                subtitle: const Text('Message an administrator'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const SupportPage()),
                ),
              ),
            ),
            const SizedBox(height: 12),
            const SettingsAboutTile(),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;

  const _SectionTitle(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        title,
        style: const TextStyle(
          color: AppColors.tealGreen,
          fontWeight: FontWeight.bold,
          letterSpacing: 1.2,
        ),
      ),
    );
  }
}
