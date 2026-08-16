import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../../../features/orders/domain/purchase_order.dart';
import '../../../features/orders/presentation/cubit/orders_cubit.dart';
import '../../../features/orders/presentation/cubit/orders_state.dart';

class PurchaseActionButtons extends StatelessWidget {
  final PurchaseOrder purchase;

  const PurchaseActionButtons({super.key, required this.purchase});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<OrdersCubit, OrdersState>(
      builder: (context, state) {
        final busy = state.mutatingOrderId != null;
        final busyThis = state.mutatingOrderId == purchase.id;

        return Row(
          children: [
            Expanded(
              child: ElevatedButton.icon(
                onPressed: busy
                    ? null
                    : () => _confirm(
                          context,
                          title: 'Receive order',
                          message:
                              'Receiving adds these items to your inventory. Continue?',
                          action: () =>
                              context.read<OrdersCubit>().receiveOrder(
                                    purchase.id,
                                  ),
                        ),
                icon: busyThis
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.check),
                label: const Text("Receive"),
              ),
            ),

            const SizedBox(width: 10),

            Expanded(
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.errorRed,
                ),
                onPressed: busy
                    ? null
                    : () => _confirm(
                          context,
                          title: 'Cancel order',
                          message: 'This cancels the order. Continue?',
                          action: () =>
                              context.read<OrdersCubit>().cancelOrder(
                                    purchase.id,
                                  ),
                        ),
                icon: const Icon(Icons.close),
                label: const Text("Cancel"),
              ),
            ),
          ],
        );
      },
    );
  }

  Future<void> _confirm(
    BuildContext context, {
    required String title,
    required String message,
    required Future<bool> Function() action,
  }) async {
    final messenger = ScaffoldMessenger.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Back'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    final ok = await action();
    if (!ok) {
      messenger.showSnackBar(
        const SnackBar(content: Text('The order could not be updated.')),
      );
    }
  }
}
