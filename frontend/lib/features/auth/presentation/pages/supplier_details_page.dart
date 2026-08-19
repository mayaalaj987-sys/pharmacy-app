import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/purchases/cart_fab.dart';
import '../../../../core/widgets/quantity_dialog.dart';
import '../../../purchase_cart/presentation/cubit/purchase_cart_cubit.dart';
import '../../../purchase_cart/presentation/cubit/purchase_cart_state.dart';
import '../../../purchase_cart/presentation/pages/purchase_cart_page.dart';
import '../../../suppliers/domain/supplier.dart';
import '../../../suppliers/presentation/cubit/suppliers_cubit.dart';
import '../../../suppliers/presentation/cubit/suppliers_state.dart';

/// One supplier's catalogue, from which things are put in the cart.
///
/// Adding no longer places an order. The pharmacist collects what they need
/// across several suppliers, compares, and buys once — which is the only way
/// to notice that the same box is cheaper two streets away.
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
    context.read<PurchaseCartCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    final supplier = widget.supplier;

    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: AppBar(title: Text(supplier.name)),
      floatingActionButton: const PurchaseCartFab(),
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
            return const Center(child: Text('No medicines available'));
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: medicines.length,
            itemBuilder: (context, index) => _card(medicines[index]),
          );
        },
      ),
    );
  }

  Widget _card(SupplierMedicine medicine) {
    final soldOut = medicine.availableQuantity <= 0;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    medicine.name,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    // What this pharmacy is charged. The supplier's suggested
                    // shelf price sits underneath, where it cannot be mistaken
                    // for the bill.
                    Text(
                      money(medicine.price),
                      style: const TextStyle(
                        color: AppColors.darkGreen,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      'sells for ~${money(medicine.suggestedRetail)}',
                      style: const TextStyle(
                        fontSize: 10,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              medicine.category,
              style: TextStyle(color: Colors.grey.shade600),
            ),
            const SizedBox(height: 4),
            Text('Available: ${medicine.availableQuantity}'),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: BlocBuilder<PurchaseCartCubit, PurchaseCartState>(
                builder: (context, cart) => ElevatedButton.icon(
                  key: ValueKey('supplier-buy-${medicine.id}'),
                  onPressed: soldOut || cart.busy
                      ? null
                      : () => _addToCart(medicine),
                  icon: const Icon(Icons.add_shopping_cart),
                  label: Text(soldOut ? 'Out of stock' : 'Add to cart'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Asks how many to put in the cart.
  ///
  /// The figure shown is only what we last saw of a catalogue shared between
  /// pharmacies, so nothing is enforced here. The cart flags a line the
  /// supplier can no longer fill, and checkout refuses it under a row lock.
  Future<void> _addToCart(SupplierMedicine medicine) async {
    final quantity = await askQuantity(
      context,
      title: medicine.name,
      subtitle: 'Available from this supplier: ${medicine.availableQuantity}',
      footnote: 'Unit cost ${money(medicine.price)}',
      initial: medicine.availableQuantity < 10
          ? medicine.availableQuantity
          : 10,
      confirmLabel: 'Add to cart',
      fieldKey: const ValueKey('order-quantity-field'),
      confirmKey: const ValueKey('order-confirm-button'),
    );

    if (quantity == null || !mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    final cart = context.read<PurchaseCartCubit>();
    final ok = await cart.add(medicine.id, quantity);
    if (!mounted) return;

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? null : AppColors.errorRed,
        content: Text(
          ok
              ? '$quantity x ${medicine.name} added. Nothing is bought until '
                    'you check out.'
              : userFacingError(
                  cart.state.error,
                  fallback: 'Could not add that to the cart.',
                ),
        ),
        action: ok
            ? SnackBarAction(
                label: 'View cart',
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const PurchaseCartPage()),
                ),
              )
            : null,
      ),
    );
  }
}
