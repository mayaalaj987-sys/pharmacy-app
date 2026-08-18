import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../orders/presentation/cubit/orders_cubit.dart';
import '../../domain/purchase_cart.dart';
import '../cubit/purchase_cart_cubit.dart';
import '../cubit/purchase_cart_state.dart';

/// The purchase cart: everything about to be bought, grouped by who sells it.
///
/// Grouped rather than listed flat because the split is real — pressing Buy
/// creates one order per supplier, each shipping and invoicing separately, and
/// finding that out afterwards is a surprise.
class PurchaseCartPage extends StatefulWidget {
  const PurchaseCartPage({super.key});

  @override
  State<PurchaseCartPage> createState() => _PurchaseCartPageState();
}

class _PurchaseCartPageState extends State<PurchaseCartPage> {
  String _paymentMethod = 'cash';

  @override
  void initState() {
    super.initState();
    context.read<PurchaseCartCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: 'Purchase cart',
          showNotificationBell: false,
        ),
      ),
      body: BlocBuilder<PurchaseCartCubit, PurchaseCartState>(
        builder: (context, state) {
          if (state.status == PurchaseCartStatus.loading &&
              state.cart.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (state.status == PurchaseCartStatus.failure &&
              state.cart.isEmpty) {
            return _retry(state);
          }

          if (state.cart.isEmpty) return _empty();

          return Column(
            children: [
              Expanded(child: _lines(state)),
              _footer(state),
            ],
          );
        },
      ),
    );
  }

  Widget _retry(PurchaseCartState state) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              userFacingError(
                state.error,
                fallback: 'Unable to load the cart.',
              ),
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.errorRed),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => context.read<PurchaseCartCubit>().load(),
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _empty() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.shopping_cart_outlined,
              size: 56,
              color: Colors.grey.shade400,
            ),
            const SizedBox(height: 16),
            const Text(
              'Your cart is empty.',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Add medicines from any supplier, and anything that runs low will '
              'turn up here on its own.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: Colors.black54),
            ),
          ],
        ),
      ),
    );
  }

  Widget _lines(PurchaseCartState state) {
    final cart = state.cart;

    return RefreshIndicator(
      onRefresh: () => context.read<PurchaseCartCubit>().load(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
        children: [
          if (cart.suggestedCount > 0) _suggestionBanner(cart.suggestedCount),
          if (cart.unavailableCount > 0) _shortageBanner(cart.unavailableCount),
          ...cart.groups.map((group) => _group(state, group)),
          const SizedBox(height: 8),
          Center(
            child: TextButton.icon(
              key: const ValueKey('cart-clear-button'),
              onPressed: state.busy ? null : _confirmClear,
              icon: const Icon(Icons.delete_outline, size: 18),
              label: const Text('Empty the cart'),
              style: TextButton.styleFrom(foregroundColor: AppColors.errorRed),
            ),
          ),
        ],
      ),
    );
  }

  Widget _suggestionBanner(int count) {
    return _banner(
      key: const ValueKey('cart-suggestions-banner'),
      icon: Icons.auto_awesome,
      colour: AppColors.darkGreen,
      text:
          '$count item(s) were added for you because stock ran low. '
          'Nothing is bought until you press Buy.',
    );
  }

  Widget _shortageBanner(int count) {
    return _banner(
      key: const ValueKey('cart-shortage-banner'),
      icon: Icons.warning_amber_rounded,
      colour: AppColors.errorRed,
      text:
          '$count item(s) are no longer available in full. Lower the quantity '
          'or switch supplier before buying.',
    );
  }

  Widget _banner({
    required Key key,
    required IconData icon,
    required Color colour,
    required String text,
  }) {
    return Container(
      key: key,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colour.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: colour),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 12))),
        ],
      ),
    );
  }

  Widget _group(PurchaseCartState state, CartSupplierGroup group) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.local_shipping_outlined, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    group.supplierName,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                ),
              ],
            ),
            if (group.supplierAddress.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(left: 26, top: 2),
                child: Text(
                  group.supplierAddress,
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
              ),
            const Divider(height: 20),
            ...group.items.map((line) => _line(state, line)),
            Align(
              alignment: Alignment.centerRight,
              child: Text(
                'Order total ${money(group.subtotal)}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _line(PurchaseCartState state, CartLine line) {
    final busy = state.mutatingItemId == line.id;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      line.name,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    Text(
                      '${money(line.unitCost)} each',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                money(line.subtotal),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ],
          ),
          if (line.suggested)
            _tag(
              key: ValueKey('cart-suggested-${line.id}'),
              icon: Icons.auto_awesome,
              colour: AppColors.darkGreen,
              text: 'Added for you — stock ran low',
            ),
          if (!line.available)
            _tag(
              key: ValueKey('cart-short-${line.id}'),
              icon: Icons.warning_amber_rounded,
              colour: AppColors.errorRed,
              text: 'Only ${line.availableQuantity} left at this supplier',
            ),
          const SizedBox(height: 6),
          Row(
            children: [
              _stepper(line, busy),
              const Spacer(),
              IconButton(
                key: ValueKey('cart-remove-${line.id}'),
                visualDensity: VisualDensity.compact,
                onPressed: state.busy
                    ? null
                    : () => context.read<PurchaseCartCubit>().remove(line.id),
                icon: const Icon(Icons.delete_outline, size: 20),
                color: AppColors.errorRed,
              ),
              if (busy)
                const SizedBox.square(
                  dimension: 14,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
            ],
          ),
          if (line.cheaperElsewhere != null) _switchOffer(state, line),
        ],
      ),
    );
  }

  Widget _tag({
    required Key key,
    required IconData icon,
    required Color colour,
    required String text,
  }) {
    return Padding(
      key: key,
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        children: [
          Icon(icon, size: 13, color: colour),
          const SizedBox(width: 4),
          Text(text, style: TextStyle(fontSize: 11, color: colour)),
        ],
      ),
    );
  }

  Widget _stepper(CartLine line, bool busy) {
    return Row(
      children: [
        IconButton(
          key: ValueKey('cart-less-${line.id}'),
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
          onPressed: busy || line.quantity <= 1
              ? null
              : () => context.read<PurchaseCartCubit>().setQuantity(
                  line.id,
                  line.quantity - 1,
                ),
          icon: const Icon(Icons.remove_circle_outline, size: 20),
        ),
        GestureDetector(
          key: ValueKey('cart-quantity-${line.id}'),
          onTap: busy ? null : () => _askQuantity(line),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              border: Border.all(color: Colors.grey.shade300),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              '${line.quantity}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ),
        IconButton(
          key: ValueKey('cart-more-${line.id}'),
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
          onPressed: busy
              ? null
              : () => context.read<PurchaseCartCubit>().setQuantity(
                  line.id,
                  line.quantity + 1,
                ),
          icon: const Icon(Icons.add_circle_outline, size: 20),
        ),
      ],
    );
  }

  Widget _switchOffer(PurchaseCartState state, CartLine line) {
    final cheaper = line.cheaperElsewhere!;

    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Material(
        color: AppColors.lightGreen.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          key: ValueKey('cart-switch-${line.id}'),
          borderRadius: BorderRadius.circular(10),
          onTap: state.busy
              ? null
              : () => context.read<PurchaseCartCubit>().switchSupplier(
                  line.id,
                  cheaper.medicineId,
                ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            child: Row(
              children: [
                const Icon(Icons.savings_outlined, size: 16),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${cheaper.supplierName} sells it at ${money(cheaper.unitCost)} '
                    '— save ${money(cheaper.saving)}',
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
                const Text(
                  'Switch',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppColors.darkGreen,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _footer(PurchaseCartState state) {
    final cart = state.cart;
    final buying = state.mutatingItemId == -1;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
      decoration: BoxDecoration(
        color: AppColors.white,
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: .06), blurRadius: 8),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: _payment('cash', 'Cash', Icons.payments_outlined),
              ),
              const SizedBox(width: 8),
              Expanded(child: _payment('card', 'Card', Icons.credit_card)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      money(cart.total),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: AppColors.darkGreen,
                      ),
                    ),
                    Text(
                      '${cart.itemCount} item(s) · '
                      '${cart.groups.length} order(s)',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ),
              FilledButton.icon(
                key: const ValueKey('cart-buy-button'),
                onPressed: state.busy ? null : _confirmBuy,
                icon: buying
                    ? const SizedBox.square(
                        dimension: 14,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.shopping_bag_outlined, size: 18),
                label: const Text('Buy'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _payment(String value, String label, IconData icon) {
    final selected = _paymentMethod == value;

    return OutlinedButton.icon(
      key: ValueKey('cart-payment-$value'),
      onPressed: () => setState(() => _paymentMethod = value),
      icon: Icon(icon, size: 16),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        foregroundColor: selected ? AppColors.darkGreen : Colors.black54,
        backgroundColor: selected
            ? AppColors.lightGreen.withValues(alpha: .15)
            : null,
        side: BorderSide(
          color: selected ? AppColors.darkGreen : Colors.grey.shade300,
        ),
      ),
    );
  }

  Future<void> _confirmClear() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Empty the cart?'),
        content: const Text(
          'Everything waiting to be bought is removed. Nothing has been '
          'ordered, so nothing is cancelled.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep it'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: TextButton.styleFrom(foregroundColor: AppColors.errorRed),
            child: const Text('Empty'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;
    await context.read<PurchaseCartCubit>().clear();
  }

  Future<void> _askQuantity(CartLine line) async {
    final controller = TextEditingController(text: '${line.quantity}');
    String? error;

    final quantity = await showDialog<int>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(line.name),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${line.availableQuantity} available at this supplier'),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('cart-quantity-field'),
                controller: controller,
                autofocus: true,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'Quantity',
                  errorText: error,
                  border: const OutlineInputBorder(),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            FilledButton(
              key: const ValueKey('cart-quantity-confirm'),
              onPressed: () {
                final value = int.tryParse(controller.text.trim());
                if (value == null || value < 1) {
                  setDialogState(
                    () => error = 'Enter a quantity of 1 or more.',
                  );

                  return;
                }
                Navigator.pop(dialogContext, value);
              },
              child: const Text('Set'),
            ),
          ],
        ),
      ),
    );

    // Disposed after the dialog is gone, never while its route animates out.
    controller.dispose();
    if (quantity == null || !mounted) return;

    await context.read<PurchaseCartCubit>().setQuantity(line.id, quantity);
  }

  Future<void> _confirmBuy() async {
    final cart = context.read<PurchaseCartCubit>().state.cart;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Place this order?'),
        content: Text(
          cart.groups.length == 1
              ? '${money(cart.total)} to ${cart.groups.first.supplierName}, '
                    'paid by $_paymentMethod.'
              : '${money(cart.total)} across ${cart.groups.length} suppliers, '
                    'paid by $_paymentMethod. Each supplier is invoiced '
                    'separately, so this becomes ${cart.groups.length} orders.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            key: const ValueKey('cart-buy-confirm'),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Buy'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<PurchaseCartCubit>();
    final orders = context.read<OrdersCubit>();

    final placed = await cubit.checkout(_paymentMethod);
    if (!mounted) return;

    // The purchases screen is where they are received, so it must not be stale.
    if (placed != null) await orders.load();
    if (!mounted) return;

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: placed == null ? AppColors.errorRed : null,
        content: Text(
          placed == null
              ? userFacingError(
                  cubit.state.error,
                  fallback: 'Your order could not be placed.',
                )
              : 'Placed $placed order(s). Receive them from Purchases when '
                    'they arrive.',
        ),
      ),
    );

    if (placed != null && mounted) Navigator.pop(context);
  }
}
