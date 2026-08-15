import 'package:flutter/material.dart';

import '../../features/auth/data/models/medicine_model.dart';
import '../data/medicine_data.dart';
import '../data/sales_data.dart';
import '../theme/app_colors.dart';

class StatsSection extends StatelessWidget {
  const StatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    /// Total Revenue
    double revenue = 0;

    for (var sale in sales) {
      revenue += double.tryParse(sale.totalAmount) ?? 0;
    }

    /// Today Sales
    final todaySales = sales.length;

    /// Low Stock
    final lowStock = medicines.where((medicine) {
      final quantity = int.tryParse(medicine.quantity) ?? 0;

      final reorder = int.tryParse(medicine.reorderLevel) ?? 0;

      return quantity <= reorder;
    }).length;

    /// Expiring
    final expiring = medicines.where((medicine) {
      if (medicine.expiryDate.isEmpty) {
        return false;
      }

      return true;
    }).length;

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
          value: todaySales.toString(),
          icon: Icons.shopping_cart,
          color: AppColors.lightGreen,
          percent: "Orders",
        ),

        _StatCard(
          title: "Revenue",
          value: "\$${revenue.toStringAsFixed(2)}",
          icon: Icons.attach_money,
          color: Colors.blue,
          percent: "Total Revenue",
        ),

        _StatCard(
          title: "Low Stock",
          value: lowStock.toString(),
          icon: Icons.warning,
          color: AppColors.warningYellow,
          percent: "Need Restock",
        ),

        _StatCard(
          title: "Expiring",
          value: expiring.toString(),
          icon: Icons.error,
          color: AppColors.errorRed,
          percent: "Medicines",
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
