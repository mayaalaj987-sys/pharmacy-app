import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class RecentSalesSection extends StatelessWidget {
  const RecentSalesSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          "Recent Sales",
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        Container(
          height: 120,
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Center(
            child: Text("Last 4 Invoices"),
          ),
        ),
      ],
    );
  }
}