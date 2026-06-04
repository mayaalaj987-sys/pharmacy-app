import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class StatsSection extends StatelessWidget {
  const StatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      shrinkWrap: true,
      crossAxisCount: 2,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 1.5,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      children: [
        _StatCard(
          title: "Today Sales",
          value: "1,250\$",
          icon: Icons.shopping_cart,
          color: AppColors.lightGreen,
          percent: "+12.5%",
        ),
        _StatCard(
          title: "Revenue",
          value: "45,280\$",
          icon: Icons.attach_money,
          color: Colors.blue,
          percent: "+8.3%",
        ),
        _StatCard(
          title: "Low Stock",
          value: "12",
          icon: Icons.warning,
          color: AppColors.warningYellow,
          percent: "View items",
        ),
        _StatCard(
          title: "Expiring",
          value: "8",
          icon: Icons.error,
          color: AppColors.errorRed,
          percent: "View items",
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final String percent;

  const _StatCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    required this.percent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color),

          const Spacer(),

          Text(
            value,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),

          Text(title),

          Text(percent, style: TextStyle(color: color, fontSize: 12)),
        ],
      ),
    );
  }
}
