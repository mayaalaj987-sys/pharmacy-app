import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';

import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
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
  void dispose() {
    usernameController.dispose();

    passwordController.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is AuthSuccess) {
          Navigator.pushReplacement(
            context,

            MaterialPageRoute(builder: (_) => const MainNavigationPage()),
          );
        }

        if (state is AuthError) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(state.message)));
        }
      },

      builder: (context, state) {
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
                      width: 150,

                      height: 150,

                      decoration: BoxDecoration(
                        color: AppColors.white,

                        borderRadius: BorderRadius.circular(30),
                      ),

                      child: Image.asset('assets/images/login.jpg'),
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

                    style: TextStyle(
                      fontSize: 15,

                      color: AppColors.secondaryText,
                    ),
                  ),

                  const SizedBox(height: 40),

                  CustomTextField(
                    controller: usernameController,

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

                  const SizedBox(height: 34),

                  CustomButton(
                    text: state is AuthLoading ? "Loading..." : "Login",

                    onPressed: state is AuthLoading
                        ? null
                        : () {
                            context.read<AuthCubit>().login(
                              usernameController.text,

                              passwordController.text,
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
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
