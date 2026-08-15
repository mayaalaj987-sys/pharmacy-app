import 'dart:io';

import 'package:flutter/material.dart';

import '../../../features/auth/presentation/pages/settings_edit_profile_page.dart';
import '../../theme/app_colors.dart';

class SettingsProfileCard extends StatelessWidget {
  const SettingsProfileCard({super.key});

  @override
  Widget build(BuildContext context) {
    // بيانات مؤقتة للواجهة فقط

    const String username = "Maya Pharmacy";

    const String email = "maya@gmail.com";

    const String status = "Active";

    return Container(
      padding: const EdgeInsets.all(20),

      decoration: BoxDecoration(
        color: AppColors.white,

        borderRadius: BorderRadius.circular(20),

        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),

            blurRadius: 10,

            offset: const Offset(0, 3),
          ),
        ],
      ),

      child: Row(
        children: [
          CircleAvatar(
            radius: 35,

            backgroundColor: AppColors.veryLightGreen,

            child: Text(
              username[0],

              style: const TextStyle(
                fontSize: 25,

                fontWeight: FontWeight.bold,

                color: AppColors.darkGreen,
              ),
            ),
          ),

          const SizedBox(width: 16),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(
                  username,

                  style: const TextStyle(
                    fontSize: 20,

                    fontWeight: FontWeight.bold,
                  ),
                ),

                const SizedBox(height: 5),

                Text(
                  email,

                  style: const TextStyle(color: AppColors.secondaryText),
                ),

                const SizedBox(height: 5),

                Text(
                  status,

                  style: const TextStyle(
                    color: AppColors.successGreen,

                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),

          IconButton(
            onPressed: () {
              Navigator.push(
                context,

                MaterialPageRoute(
                  builder: (_) => const SettingsEditProfilePage(),
                ),
              );
            },

            icon: const Icon(Icons.edit),

            color: AppColors.tealGreen,
          ),
        ],
      ),
    );
  }
}
