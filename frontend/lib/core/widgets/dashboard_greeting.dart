import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class DashboardGreeting extends StatelessWidget {
  const DashboardGreeting({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          "Welcome Back",
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 4),
        Text(
          "Today Overview",
          style: TextStyle(color: AppColors.grey),
        ),
      ],
    );
  }
}