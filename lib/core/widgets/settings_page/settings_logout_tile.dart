import 'package:flutter/material.dart';

import '../../../features/auth/presentation/pages/login_page.dart';

import '../../theme/app_colors.dart';

class SettingsLogoutTile extends StatelessWidget {
  const SettingsLogoutTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.white,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

      child: ListTile(
        leading: const Icon(Icons.logout, color: AppColors.pendingOrange),

        title: const Text(
          "Logout",

          style: TextStyle(fontWeight: FontWeight.bold),
        ),

        onTap: () {
          showDialog(
            context: context,

            builder: (_) {
              return AlertDialog(
                title: const Text("Logout"),

                content: const Text("Are you sure you want to logout?"),

                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },

                    child: const Text("Cancel"),
                  ),

                  ElevatedButton(
                    onPressed: () {
                      Navigator.pushAndRemoveUntil(
                        context,

                        MaterialPageRoute(builder: (_) => const LoginPage()),

                        (route) => false,
                      );
                    },

                    child: const Text("Logout"),
                  ),
                ],
              );
            },
          );
        },
      ),
    );
  }
}
