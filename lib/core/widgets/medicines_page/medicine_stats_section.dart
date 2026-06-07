import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import 'medicine_stat_card.dart';

class MedicineStatsSection extends StatelessWidget {
  const MedicineStatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),

      child: Row(
        children: [
          Expanded(
            child: MedicineStatCard(
              title: "Expiring",
              value: "8",
              color: AppColors.pendingOrange,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: MedicineStatCard(
              title: "Low",
              value: "0",
              color: AppColors.errorRed,
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: MedicineStatCard(
              title: "Total",
              value: "11",
              color: AppColors.tealGreen,
            ),
          ),
        ],
      ),
    );
  }
}