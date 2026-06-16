import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

class SettingsAboutTile extends StatelessWidget {
  const SettingsAboutTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.white,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

      child: ListTile(
        leading: const Icon(Icons.info, color: AppColors.tealGreen),

        title: const Text(
          "About App",

          style: TextStyle(fontWeight: FontWeight.bold),
        ),

        trailing: const Icon(Icons.arrow_forward_ios, size: 18),

        onTap: () {
          showDialog(
            context: context,

            builder: (_) {
              return AlertDialog(
                title: const Text("About"),

                content: const Text(
                  "Pharmacy Management System\n\n"
                  "Version 1.0\n\n"
                  "Built with Flutter",
                ),
              );
            },
          );
        },
      ),
    );
  }
}
