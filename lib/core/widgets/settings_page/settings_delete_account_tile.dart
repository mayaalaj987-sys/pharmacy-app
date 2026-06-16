import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

class SettingsDeleteAccountTile extends StatelessWidget {
  const SettingsDeleteAccountTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.red.shade50,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

      child: ListTile(
        leading: const Icon(Icons.delete_forever, color: AppColors.errorRed),

        title: const Text(
          "Delete Account",

          style: TextStyle(
            color: AppColors.errorRed,

            fontWeight: FontWeight.bold,
          ),
        ),

        onTap: () {
          showDialog(
            context: context,

            builder: (_) {
              return AlertDialog(
                title: const Text("Delete Account"),

                content: const Text("This action cannot be undone."),

                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.pop(context);
                    },

                    child: const Text("Cancel"),
                  ),

                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.errorRed,
                    ),

                    onPressed: () {
                      Navigator.pop(context);
                    },

                    child: const Text("Delete"),
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
