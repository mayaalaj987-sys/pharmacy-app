import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';

class AuthHeaderWidget extends StatelessWidget {
  const AuthHeaderWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 120,
          height: 120,
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(30),
          ),
          child: Image.asset('assets/images/login.jpg'),
        ),
        const SizedBox(height: 32),
        const Text(
          'Who Are You?',
          style: TextStyle(
            fontSize: 32,
            fontWeight: FontWeight.bold,
            color: AppColors.darkGreen,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Select your account type to continue',
          style: TextStyle(fontSize: 15, color: AppColors.secondaryText),
        ),
      ],
    );
  }
}
