import 'package:flutter/material.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/pos_page.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/suppliers_page.dart';

import '../../features/auth/presentation/pages/add_medicine_page.dart';
import '../theme/app_colors.dart';

class QuickActionsSection extends StatelessWidget {
  const QuickActionsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(

      children: [
        _Action(
          icon: Icons.shopping_cart,
          label: "Order",
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const SuppliersPage()),
            );

            // Purchases Page
          },

        ),

        const SizedBox(width: 25),
        _Action(
          icon: Icons.add,
          label: "New Sale",
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const PosPage()),
            );
          },
        ),
        const SizedBox(width: 25),
        _Action(
          icon: Icons.medication,
          label: "Add Medicine",
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const AddMedicinePage()),
            );
          },
        ),
        const SizedBox(width: 25),
      ],
    );
  }
}

class _Action extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  const _Action({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,

      borderRadius: BorderRadius.circular(50),

      child: Column(
        children: [
          CircleAvatar(
            backgroundColor: AppColors.darkGreen,
            child: Icon(icon, color: AppColors.white),
          ),

          const SizedBox(height: 5),

          Text(label),
        ],
      ),
    );
  }
}
