import 'package:flutter/material.dart';

import '../../../features/auth/presentation/pages/login_page.dart';
import '../../theme/app_colors.dart';

class SettingsLogoutTile extends StatelessWidget {
  const SettingsLogoutTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: const Icon(Icons.logout, color: AppColors.pendingOrange),
        title: const Text(
          "Logout",
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        trailing: const Icon(
          Icons.arrow_forward_ios,
          size: 18,
          color: AppColors.tealGreen,
        ),
        onTap: () {
          showDialog(
            context: context,
            builder: (_) {
              return AlertDialog(
                backgroundColor: AppColors.white,

                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),

                title: const Text(
                  "Logout",
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
                ),

                content: const Padding(
                  padding: EdgeInsets.only(top: 8),
                  child: Text(
                    "Are you sure you want to logout?",
                    style: TextStyle(fontSize: 15),
                  ),
                ),


                actionsPadding: const EdgeInsets.only(
                  left: 16,
                  right: 20,
                  bottom: 16,
                ),
                actions: [
                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          style: TextButton.styleFrom(
                            foregroundColor: AppColors.tealGreen,
                            backgroundColor: AppColors.veryLightGreen,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                          ),
                          onPressed: () {
                            Navigator.pop(context);
                          },
                          child: const Text(
                            "Cancel",
                            style: TextStyle(fontSize: 14),
                          ),
                        ),
                      ),

                      const SizedBox(width: 12),

                      Expanded(
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.tealGreen,
                            foregroundColor: AppColors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                            elevation: 0,
                          ),
                          onPressed: () {
                            Navigator.pushAndRemoveUntil(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const LoginPage(),
                              ),
                              (route) => false,
                            );
                          },
                          child: const Text(
                            "Logout",
                            style: TextStyle(fontSize: 14),
                          ),
                        ),
                      ),
                    ],
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
