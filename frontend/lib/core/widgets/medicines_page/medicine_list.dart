import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/inventory/presentation/cubit/inventory_cubit.dart';
import '../../../features/inventory/presentation/cubit/inventory_state.dart';
import '../../theme/app_colors.dart';
import 'medicine_card.dart';

class MedicineList extends StatelessWidget {
  final String selectedCategory;
  final String query;
  final bool readOnly;

  const MedicineList({
    super.key,
    required this.selectedCategory,
    this.query = '',
    this.readOnly = false,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<InventoryCubit, InventoryState>(
      builder: (context, state) {
        if (state.status == InventoryStatus.loading ||
            state.status == InventoryStatus.initial) {
          return const Center(child: CircularProgressIndicator());
        }

        if (state.status == InventoryStatus.failure) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    state.error?.message ?? 'Unable to load medicines.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.errorRed),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    key: const ValueKey('medicines-retry-button'),
                    onPressed: () => context.read<InventoryCubit>().load(),
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry'),
                  ),
                ],
              ),
            ),
          );
        }

        final normalizedQuery = query.trim().toLowerCase();
        final filtered = state.medicines.where((medicine) {
          final matchesCategory =
              selectedCategory == 'All' ||
              medicine.category == selectedCategory;
          final matchesQuery =
              normalizedQuery.isEmpty ||
              medicine.name.toLowerCase().contains(normalizedQuery);
          return matchesCategory && matchesQuery;
        }).toList();

        if (filtered.isEmpty) {
          return const Center(child: Text("No Medicines Found"));
        }

        return RefreshIndicator(
          onRefresh: () => context.read<InventoryCubit>().load(),
          child: ListView.builder(
            itemCount: filtered.length,
            itemBuilder: (context, index) {
              return MedicineCard(
                medicine: filtered[index],
                readOnly: readOnly,
                onRefresh: () => context.read<InventoryCubit>().load(),
              );
            },
          ),
        );
      },
    );
  }
}
