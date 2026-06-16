import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';

class SettingsEditProfilePage extends StatefulWidget {
  const SettingsEditProfilePage({super.key});

  @override
  State<SettingsEditProfilePage> createState() =>
      _SettingsEditProfilePageState();
}

class _SettingsEditProfilePageState extends State<SettingsEditProfilePage> {
  final usernameController = TextEditingController(text: "Maya Pharmacy");

  final emailController = TextEditingController(text: "maya@gmail.com");

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Edit Profile")),

      body: Padding(
        padding: const EdgeInsets.all(20),

        child: Column(
          children: [
            CircleAvatar(
              radius: 45,

              backgroundColor: AppColors.veryLightGreen,

              child: const Icon(
                Icons.person,

                size: 50,

                color: AppColors.darkGreen,
              ),
            ),

            const SizedBox(height: 30),

            CustomTextField(
              controller: usernameController,

              hint: "Username",

              prefixIcon: Icons.person,
            ),

            const SizedBox(height: 20),

            CustomTextField(
              controller: emailController,

              hint: "Email",

              prefixIcon: Icons.email,
            ),

            const SizedBox(height: 30),

            CustomButton(
              text: "Save Changes",

              onPressed: () {
                Navigator.pop(context);

                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text("Profile Updated Successfully")),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
