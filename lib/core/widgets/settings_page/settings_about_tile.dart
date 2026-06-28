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
                  borderRadius: BorderRadius.circular(18),
                ),

                title: const Text(
                  "About",
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
                ),

                content: const Padding(
                  padding: EdgeInsets.only(top: 8),
                  child: Text(
                    "Pharmacy Management System\n\n"
                    "Version 1.0\n\n"
                    "Built with Flutter",
                    style: TextStyle(fontSize: 15),
                  ),
                ),


                actionsPadding: const EdgeInsets.only(
                  left: 16,
                  right: 20,
                  bottom: 16,
                ),

              );
            },
          );
        },
      ),
    );
  }
}
