import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

class SettingsRateTile extends StatefulWidget {
  const SettingsRateTile({super.key});

  @override
  State<SettingsRateTile> createState() => _SettingsRateTileState();
}

class _SettingsRateTileState extends State<SettingsRateTile> {
  int selectedRating = 0;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: const Icon(Icons.star, color: AppColors.pendingOrange),
        title: const Text(
          "Rate App",
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
              return StatefulBuilder(
                builder: (context, setDialogState) {
                  return AlertDialog(
                    backgroundColor: AppColors.white,

                    title: const Text(
                      "Rate Application",
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),

                    content: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text("Choose your rating"),
                        const SizedBox(height: 20),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List.generate(5, (index) {
                            return IconButton(
                              onPressed: () {
                                setDialogState(() {
                                  selectedRating = index + 1;
                                });
                              },
                              icon: Icon(
                                index < selectedRating
                                    ? Icons.star
                                    : Icons.star_border,
                                color: AppColors.pendingOrange,
                                size: 35,
                              ),
                            );
                          }),
                        ),

                        const SizedBox(height: 10),

                        Text(
                          "$selectedRating / 5",
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: AppColors.tealGreen,
                          ),
                        ),
                      ],
                    ),

                    actionsPadding: const EdgeInsets.only(
                      left: 16,
                      right: 20,
                      bottom: 24,
                    ),

                    actions: [
                      Row(
                        children: [
                          Expanded(
                            child: TextButton(
                              style: TextButton.styleFrom(
                                foregroundColor: AppColors.tealGreen,
                                backgroundColor: AppColors.veryLightGreen,
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                minimumSize: const Size(0, 36),
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

                          const SizedBox(width: 11),

                          Expanded(
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.tealGreen,
                                foregroundColor: AppColors.white,
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                minimumSize: const Size(0, 36),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                elevation: 0,
                              ),
                              onPressed: () {
                                Navigator.pop(context);

                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    backgroundColor: AppColors.tealGreen,
                                    content: Text(
                                      "Thanks for rating $selectedRating stars ⭐",
                                    ),
                                  ),
                                );
                              },
                              child: const Text(
                                "Submit",
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
          );
        },
      ),
    );
  }
}
