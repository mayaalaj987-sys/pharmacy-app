import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class QuickActionsSection extends StatelessWidget {
  const QuickActionsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        TextButton.icon(label: Text("New Sale"), onPressed:(){},) ,//_Action(icon: Icons.add, label: "New Sale"),
        const _Action(icon: Icons.medication, label: "Add Medicine"),
        const _Action(icon: Icons.shopping_cart, label: "Order"),
        TextButton(
          onPressed: () {},
          child: const Text(
            "See All",
            style: TextStyle(color: AppColors.darkGrey),
          ),
        ),
      ],
    );
  }
}

class _Action extends StatelessWidget {
  final IconData icon;
  final String label;

  const _Action({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        CircleAvatar(
          backgroundColor: AppColors.darkGreen,
          child: Icon(icon, color: AppColors.white),
        ),
        const SizedBox(height: 5),
        Text(label),
      ],
    );
  }
}
