import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../features/inventory/presentation/cubit/inventory_cubit.dart';
import '../../../features/inventory/presentation/cubit/inventory_state.dart';
import 'medicine_stat_card.dart';

class MedicineStatsSection extends StatelessWidget {
  const MedicineStatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<InventoryCubit, InventoryState>(
      builder: (context, state) {
        final medicines = state.medicines;

        final totalMedicines = medicines.length;

        final lowStock =
            medicines.where((m) => m.isLowStock && m.quantity > 0).length;

        final expiring = medicines.where((m) {
          final expiry = m.expireDate;
          if (expiry == null) return false;
          return expiry.difference(DateTime.now()).inDays <= 90;
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
      },
    );
  }
}
