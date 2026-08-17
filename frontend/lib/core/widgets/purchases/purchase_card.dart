import 'package:flutter/material.dart';

import '../../../features/orders/domain/purchase_order.dart';
import 'purchase_status_badge.dart';
import 'purchase_action_buttons.dart';
import '../../format/money.dart';

class PurchaseCard extends StatelessWidget {
  final PurchaseOrder purchase;

  const PurchaseCard({super.key, required this.purchase});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),

      decoration: BoxDecoration(
        color: Colors.white,

        borderRadius: BorderRadius.circular(20),

        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(.05),

            blurRadius: 15,

            offset: const Offset(0, 5),
          ),
        ],
      ),

      child: Padding(
        padding: const EdgeInsets.all(18),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: [
                Expanded(
                  child: Text(
                    purchase.medicinesSummary,

                    style: const TextStyle(
                      fontSize: 18,

                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),

                PurchaseStatusBadge(status: purchase.statusLabel),
              ],
            ),

            const SizedBox(height: 14),

            _buildInfo(
              Icons.local_shipping_outlined,

              "Supplier",

              purchase.supplierName,
            ),

            _buildInfo(
              Icons.inventory_2_outlined,

              "Quantity",

              purchase.totalQuantity.toString(),
            ),

            _buildInfo(Icons.attach_money, "Total", money(purchase.totalPrice)),

            const SizedBox(height: 14),

            if (purchase.isPending) PurchaseActionButtons(purchase: purchase),
          ],
        ),
      ),
    );
  }

  Widget _buildInfo(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),

      child: Row(
        children: [
          Icon(icon, size: 18, color: Colors.grey),

          const SizedBox(width: 8),

          Text("$title: ", style: const TextStyle(fontWeight: FontWeight.w600)),

          Text(value),
        ],
      ),
    );
  }
}
