import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../orders/presentation/cubit/orders_cubit.dart';
import '../../../suppliers/domain/supplier.dart';
import '../../../suppliers/presentation/cubit/suppliers_cubit.dart';
import '../../../suppliers/presentation/cubit/suppliers_state.dart';
import '../../../../core/format/money.dart';

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
              state.medicinesBySupplier[supplier.id] ??
              const <SupplierMedicine>[];
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
                            money(medicine.price),

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
                          key: ValueKey('supplier-buy-${medicine.id}'),
                          onPressed: medicine.availableQuantity <= 0
                              ? null
                              : () => _order(supplier.id, medicine),

                          icon: const Icon(Icons.shopping_cart),

                          label: Text(
                            medicine.availableQuantity <= 0
                                ? "Out of stock"
                                : "Order",
                          ),
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

  /// Asks how many units to order, then places the order.
  ///
  /// The supplier catalogue is shared between pharmacies, so the figure shown
  /// here can already be stale by the time the request lands. The dialog caps
  /// input at what we last saw, and the backend re-checks under a row lock and
  /// answers with the real remaining amount if someone got there first.
  Future<void> _order(int supplierId, SupplierMedicine medicine) async {
    final available = medicine.availableQuantity;
    final controller = TextEditingController(
      text: (available < 10 ? available : 10).toString(),
    );
    String? error;

    final quantity = await showDialog<int>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('Order ${medicine.name}'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Available from this supplier: $available'),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('order-quantity-field'),
                controller: controller,
                autofocus: true,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'Quantity',
                  errorText: error,
                  border: const OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Unit cost ${money(medicine.price)}',
                style: const TextStyle(color: Colors.grey),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            FilledButton(
              key: const ValueKey('order-confirm-button'),
              onPressed: () {
                final value = int.tryParse(controller.text.trim());
                if (value == null || value < 1) {
                  setDialogState(
                    () => error = 'Enter a quantity of 1 or more.',
                  );
                  return;
                }
                if (value > available) {
                  setDialogState(() => error = 'Only $available available.');
                  return;
                }
                Navigator.pop(dialogContext, value);
              },
              child: const Text('Place order'),
            ),
          ],
        ),
      ),
    );

    controller.dispose();
    if (quantity == null || !mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    final orders = context.read<OrdersCubit>();
    final suppliers = context.read<SuppliersCubit>();

    final ok = await orders.createOrder(
      supplierId: supplierId,
      medicineId: medicine.id,
      quantity: quantity,
    );

    if (!mounted) return;

    // Availability changed either way: refresh so the next order sees the truth.
    await suppliers.loadMedicines(supplierId);
    if (!mounted) return;

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? null : AppColors.errorRed,
        content: Text(
          ok
              ? 'Ordered $quantity x ${medicine.name}'
              : userFacingError(
                  orders.state.error,
                  fallback: 'Could not create the order.',
                ),
        ),
      ),
    );
  }
}
