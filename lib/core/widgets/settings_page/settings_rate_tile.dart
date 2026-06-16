import 'package:flutter/material.dart';

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
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

      child: ListTile(
        leading: const Icon(Icons.star, color: Colors.amber),

        title: const Text(
          "Rate App",

          style: TextStyle(fontWeight: FontWeight.bold),
        ),

        trailing: const Icon(Icons.arrow_forward_ios, size: 18),

        onTap: () {
          showDialog(
            context: context,

            builder: (_) {
              return StatefulBuilder(
                builder: (context, setDialogState) {
                  return AlertDialog(
                    title: const Text("Rate Application"),

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

                                color: Colors.amber,

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
                          ),
                        ),
                      ],
                    ),

                    actions: [
                      TextButton(
                        onPressed: () {
                          Navigator.pop(context);
                        },

                        child: const Text("Cancel"),
                      ),

                      ElevatedButton(
                        onPressed: () {
                          Navigator.pop(context);

                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                "Thanks for rating $selectedRating stars ⭐",
                              ),
                            ),
                          );
                        },

                        child: const Text("Submit"),
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
