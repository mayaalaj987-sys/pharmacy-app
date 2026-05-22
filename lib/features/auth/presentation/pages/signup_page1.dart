import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/theme/app_colors.dart';

import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';

import 'signup_page2.dart';

class SignupPage1 extends StatefulWidget {
  const SignupPage1({super.key});

  @override
  State<SignupPage1> createState() => _SignupPage1State();
}

class _SignupPage1State extends State<SignupPage1> {
  final usernameController = TextEditingController();

  final emailController = TextEditingController();

  final passwordController = TextEditingController();

  File? selectedImage;

  Future<void> pickImage() async {
    final image = await ImagePicker().pickImage(source: ImageSource.gallery);

    if (image != null) {
      setState(() {
        selectedImage = File(image.path);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              const SizedBox(height: 20),

              const Text(
                'Create Account',

                style: TextStyle(
                  fontSize: 32,

                  fontWeight: FontWeight.bold,

                  color: AppColors.darkGreen,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Register your account',

                style: TextStyle(color: AppColors.secondaryText, fontSize: 15),
              ),

              const SizedBox(height: 40),

              Center(
                child: GestureDetector(
                  onTap: pickImage,

                  child: CircleAvatar(
                    radius: 55,

                    backgroundColor: AppColors.white,

                    backgroundImage: selectedImage != null
                        ? FileImage(selectedImage!)
                        : null,

                    child: selectedImage == null
                        ? const Icon(
                            Icons.camera_alt,

                            size: 38,

                            color: AppColors.tealGreen,
                          )
                        : null,
                  ),
                ),
              ),

              const SizedBox(height: 40),

              CustomTextField(
                controller: usernameController,

                hint: 'Username',

                prefixIcon: Icons.person,
              ),

              const SizedBox(height: 20),

              CustomTextField(
                controller: emailController,

                hint: 'Email',

                prefixIcon: Icons.email,
              ),

              const SizedBox(height: 20),

              CustomTextField(
                controller: passwordController,

                hint: 'Password',

                prefixIcon: Icons.lock,

                isPassword: true,
              ),

              const SizedBox(height: 40),

              CustomButton(
                text: 'Next',

                onPressed: () {
                  Navigator.push(
                    context,

                    MaterialPageRoute(
                      builder: (_) => SignupPage2(
                        username: usernameController.text,

                        email: emailController.text,

                        password: passwordController.text,

                        profileImage: selectedImage,
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
