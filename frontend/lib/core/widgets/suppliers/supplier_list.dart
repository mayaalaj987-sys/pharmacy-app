import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/suppliers/presentation/cubit/suppliers_cubit.dart';
import '../../../features/suppliers/presentation/cubit/suppliers_state.dart';
import '../../theme/app_colors.dart';
import 'supplier_card.dart';

class SupplierList extends StatelessWidget {
  const SupplierList({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SuppliersCubit, SuppliersState>(
      builder: (context, state) {
        if (state.status == SuppliersStatus.loading ||
            state.status == SuppliersStatus.initial) {
          return const Center(child: CircularProgressIndicator());
        }

        if (state.status == SuppliersStatus.failure) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    state.error?.message ?? 'Unable to load suppliers.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.errorRed),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    key: const ValueKey('suppliers-retry-button'),
                    onPressed: () => context.read<SuppliersCubit>().load(),
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry'),
                  ),
                ],
              ),
            ),
          );
        }

        if (state.suppliers.isEmpty) {
          return const Center(
            child: Text(
              "No suppliers available",
              style: TextStyle(fontSize: 16),
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () => context.read<SuppliersCubit>().load(),
          child: ListView.builder(
            itemCount: state.suppliers.length,
            itemBuilder: (context, index) {
              return SupplierCard(supplier: state.suppliers[index]);
            },
          ),
        );
      },
    );
  }
}
