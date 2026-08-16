import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../orders/presentation/cubit/orders_cubit.dart';
import '../../../suppliers/domain/supplier.dart';
import '../../../suppliers/presentation/cubit/suppliers_cubit.dart';
import '../../../suppliers/presentation/cubit/suppliers_state.dart';

class SupplierDetailsPage extends StatefulWidget {
  final Supplier supplier;

  const SupplierDetailsPage({super.key, required this.supplier});

  @override
  State<SupplierDetailsPage> createState() => _SupplierDetailsPageState();
}

class _SupplierDetailsPageState extends State<SupplierDetailsPage> {
  @override
  void initState() {
    super.initState();
    context.read<SuppliersCubit>().loadMedicines(widget.supplier.id);
  }

  @override
  Widget build(BuildContext context) {
    final supplier = widget.supplier;
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: AppBar(title: Text(supplier.name)),

      body: BlocBuilder<SuppliersCubit, SuppliersState>(
        builder: (context, state) {
          if (state.loadingMedicinesFor == supplier.id) {
            return const Center(child: CircularProgressIndicator());
          }
          if (state.medicinesError != null) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      state.medicinesError!.message,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.errorRed),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => context
                          .read<SuppliersCubit>()
                          .loadMedicines(supplier.id),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }
          final medicines =
              state.medicinesBySupplier[supplier.id] ?? const <SupplierMedicine>[];
          if (medicines.isEmpty) {
            return const Center(child: Text("No medicines available"));
          }
          return ListView.builder(
              padding: const EdgeInsets.all(16),

              itemCount: medicines.length,

              itemBuilder: (context, index) {
                final medicine = medicines[index];

                return Card(
                  margin: const EdgeInsets.only(bottom: 12),

                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),

                  child: Padding(
                    padding: const EdgeInsets.all(16),

                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,

                          children: [
                            Expanded(
                              child: Text(
                                medicine.name,

                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),

                            Text(
                              "\$${medicine.price}",

                              style: const TextStyle(
                                color: Colors.green,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 8),

                        Text(
                          medicine.category,

                          style: TextStyle(color: Colors.grey.shade600),
                        ),

                        const SizedBox(height: 4),

                        Text("Available: ${medicine.availableQuantity}"),

                        const SizedBox(height: 16),

                        SizedBox(
                          width: double.infinity,

                          child: ElevatedButton.icon(
                            onPressed: () async {
                              final messenger = ScaffoldMessenger.of(context);
                              final ok = await context
                                  .read<OrdersCubit>()
                                  .createOrder(
                                    supplierId: supplier.id,
                                    medicineId: medicine.id,
                                    quantity: 50,
                                  );
                              messenger.showSnackBar(
                                SnackBar(
                                  content: Text(
                                    ok
                                        ? "Order created for ${medicine.name}"
                                        : "Could not create the order",
                                  ),
                                ),
                              );
                            },

                            icon: const Icon(Icons.shopping_cart),

                            label: const Text("Buy"),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
        },
      ),
    );
  }
}
