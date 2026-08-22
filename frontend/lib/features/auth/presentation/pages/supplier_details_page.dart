import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/layout/responsive_layout.dart';
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
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();
    context.read<SuppliersCubit>().loadMedicines(widget.supplier.id);
    context.read<PurchaseCartCubit>().load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final supplier = widget.supplier;

    return Scaffold(
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

          final normalizedQuery = _query.trim().toLowerCase();
          final filtered = normalizedQuery.isEmpty
              ? medicines
              : medicines
                    .where(
                      (medicine) =>
                          medicine.name.toLowerCase().contains(
                            normalizedQuery,
                          ) ||
                          medicine.category.toLowerCase().contains(
                            normalizedQuery,
                          ),
                    )
                    .toList(growable: false);

          return ResponsiveContent(
            safeArea: false,
            padding: context.pagePadding.copyWith(top: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  key: const ValueKey('supplier-medicine-search'),
                  controller: _searchController,
                  autofocus: false,
                  textInputAction: TextInputAction.search,
                  onChanged: (value) => setState(() => _query = value),
                  decoration: InputDecoration(
                    labelText: 'Search this supplier',
                    hintText: 'Medicine name or category',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: _query.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Clear search',
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _query = '');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Icon(
                      Icons.medication_outlined,
                      size: 18,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      normalizedQuery.isEmpty
                          ? '${medicines.length} medicines available'
                          : '${filtered.length} of ${medicines.length} medicines',
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 220),
                    child: medicines.isEmpty
                        ? const _SupplierEmptyState(
                            key: ValueKey('empty-catalogue'),
                            icon: Icons.inventory_2_outlined,
                            title: 'No medicines available',
                            message:
                                'This supplier has not added medicines yet.',
                          )
                        : filtered.isEmpty
                        ? _SupplierEmptyState(
                            key: const ValueKey('empty-search'),
                            icon: Icons.search_off_rounded,
                            title: 'No matching medicine',
                            message:
                                'Try another name or clear “${_query.trim()}”.',
                            action: TextButton.icon(
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _query = '');
                              },
                              icon: const Icon(Icons.close_rounded),
                              label: const Text('Clear search'),
                            ),
                          )
                        : LayoutBuilder(
                            key: ValueKey('results-$normalizedQuery'),
                            builder: (context, constraints) {
                              if (constraints.maxWidth >= 700) {
                                return GridView.builder(
                                  padding: const EdgeInsets.only(bottom: 96),
                                  gridDelegate:
                                      const SliverGridDelegateWithFixedCrossAxisCount(
                                        crossAxisCount: 2,
                                        mainAxisSpacing: 12,
                                        crossAxisSpacing: 12,
                                        childAspectRatio: 1.45,
                                      ),
                                  itemCount: filtered.length,
                                  itemBuilder: (context, index) =>
                                      _card(filtered[index]),
                                );
                              }
                              return ListView.separated(
                                padding: const EdgeInsets.only(bottom: 96),
                                itemCount: filtered.length,
                                separatorBuilder: (_, _) =>
                                    const SizedBox(height: 12),
                                itemBuilder: (context, index) =>
                                    _card(filtered[index]),
                              );
                            },
                          ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _card(SupplierMedicine medicine) {
    final soldOut = medicine.availableQuantity <= 0;

    return Card(
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
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    Text(
                      'sells for ~${money(medicine.suggestedRetail)}',
                      style: TextStyle(
                        fontSize: 10,
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              medicine.category,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
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

class _SupplierEmptyState extends StatelessWidget {
  const _SupplierEmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 58, color: theme.colorScheme.outline),
            const SizedBox(height: 14),
            Text(title, style: theme.textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (action != null) ...[const SizedBox(height: 8), action!],
          ],
        ),
      ),
    );
  }
}
