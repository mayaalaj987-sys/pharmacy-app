import 'package:flutter/material.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/home_page.dart';

import '../../../../core/theme/app_colors.dart';

import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import 'main_navigation_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final usernameController = TextEditingController();

  final passwordController = TextEditingController();

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
              const SizedBox(height: 40),

              Center(
                child: Container(
                  width: 120,
                  height: 120,

                  decoration: BoxDecoration(
                    color: AppColors.white,

                    borderRadius: BorderRadius.circular(30),
                  ),

                  child: const Icon(
                    Icons.local_pharmacy,

                    size: 70,

                    color: AppColors.darkGreen,
                  ),
                ),
              ),

              const SizedBox(height: 40),

              const Text(
                'Welcome Back',

                style: TextStyle(
                  fontSize: 32,

                  fontWeight: FontWeight.bold,

                  color: AppColors.darkGreen,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Login to continue',

                style: TextStyle(fontSize: 15, color: AppColors.secondaryText),
              ),

              const SizedBox(height: 40),

              CustomTextField(
                controller: usernameController,

                hint: 'Username',

                prefixIcon: Icons.person,
              ),

              const SizedBox(height: 20),

              CustomTextField(
                controller: passwordController,

                hint: 'Password',

                prefixIcon: Icons.lock,

                isPassword: true,
              ),

              const SizedBox(height: 14),

              Align(
                alignment: Alignment.centerRight,

                child: TextButton(
                  onPressed: () {},

                  child: const Text(
                    'Forgot Password?',

                    style: TextStyle(color: AppColors.tealGreen),
                  ),
                ),
              ),

              const SizedBox(height: 20),

              CustomButton(
                text: 'Login',
                onPressed: () {
                  Navigator.pushReplacement(
                    context,

                    MaterialPageRoute(builder: (_) => const MainNavigationPage(),
                    ),
                  );
                },
              ),

              const SizedBox(height: 20),

              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: BoxDecoration(
                  color: AppColors.white,

                  borderRadius: BorderRadius.circular(18),
                ),

                child: Column(
                  children: [
                    const Icon(
                      Icons.fingerprint,

                      size: 50,

                      color: AppColors.darkGreen,
                    ),

                    const SizedBox(height: 12),

                    const Text(
                      'Login with Fingerprint',

                      style: TextStyle(
                        fontWeight: FontWeight.w600,

                        color: AppColors.darkGreen,
                      ),
                    ),

                    const SizedBox(height: 6),

                    Text(
                      'Fast & secure authentication',

                      style: TextStyle(color: AppColors.secondaryText),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 30),

              Row(
                mainAxisAlignment: MainAxisAlignment.center,

                children: [
                  const Text('Don’t have an account?'),

                  TextButton(
                    onPressed: () {},

                    child: const Text(
                      'Create Account',

                      style: TextStyle(
                        color: AppColors.darkGreen,

                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
