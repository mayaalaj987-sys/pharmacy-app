import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/data/medicine_data.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import 'medicine_stat_card.dart';

class MedicineStatsSection extends StatelessWidget {
  const MedicineStatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    /// Total Medicines
    final totalMedicines = medicines.length;

    /// Low Stock
    final lowStock = medicines.where((medicine) {
      final quantity = int.tryParse(medicine.quantity) ?? 0;

      final reorderLevel = int.tryParse(medicine.reorderLevel) ?? 0;

      return quantity <= reorderLevel && quantity > 0;
    }).length;

    /// Expiring
    final expiring = medicines.where((medicine) {
      if (medicine.expiryDate.isEmpty) {
        return false;
      }

      try {
        final expiryDate = DateTime.parse(medicine.expiryDate);

        final difference = expiryDate.difference(DateTime.now()).inDays;

        return difference <= 90;
      } catch (e) {
        return false;
      }
    }).length;

    return Padding(
      padding: const EdgeInsets.all(16),

      child: Row(
        children: [
          Expanded(
            child: MedicineStatCard(
              title: "Expiring",
              value: expiring.toString(),
              color: AppColors.pendingOrange,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: MedicineStatCard(
              title: "Low",
              value: lowStock.toString(),
              color: AppColors.errorRed,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: MedicineStatCard(
              title: "Total",
              value: totalMedicines.toString(),
              color: AppColors.tealGreen,
            ),
          ),
        ],
      ),
    );
  }
}
