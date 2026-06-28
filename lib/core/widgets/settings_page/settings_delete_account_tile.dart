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

        leading: const Icon(
          Icons.delete_forever,
          color: AppColors.errorRed,
          size: 28,
        ),

        title: const Text(
          "Delete Account",
          style: TextStyle(
            color: AppColors.errorRed,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),

        trailing: const Icon(
          Icons.arrow_forward_ios,
          size: 18,
          color: AppColors.errorRed,
        ),

        onTap: () {
          showDialog(
            context: context,
            builder: (_) {
              return AlertDialog(
                backgroundColor: Colors.white,

                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(18),
                ),

                title: const Text(
                  "Delete Account",
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: AppColors.errorRed,
                    fontSize: 20,
                  ),
                ),

                content: const Text(
                  "This action cannot be undone. Are you sure you want to continue?",
                  style: TextStyle(fontSize: 15),
                ),

                actionsPadding: const EdgeInsets.only(
                  left: 16,
                  right: 16,
                  bottom: 24,
                ),
                actions: [
                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          style: TextButton.styleFrom(
                            foregroundColor: AppColors.black,
                            backgroundColor: AppColors.veryLightGreen,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                          ),
                          onPressed: () {
                            Navigator.pop(context);
                          },
                          child: const Text(
                            "Cancel",
                            style: TextStyle(fontSize: 13),
                          ),
                        ),
                      ),

                      const SizedBox(width: 10),

                      Expanded(
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.errorRed,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                            elevation: 0,
                          ),
                          onPressed: () {
                            Navigator.pop(context);
                          },
                          child: const Text(
                            "Delete",
                            style: TextStyle(fontSize: 13),
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
