import 'package:flutter/material.dart';

import 'purchase_status_badge.dart';
import 'purchase_action_buttons.dart';

class PurchaseCard extends StatelessWidget {
  final dynamic purchase;

  final VoidCallback onUpdate;

  const PurchaseCard({
    super.key,

    required this.purchase,

    required this.onUpdate,
  });

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
                    purchase.medicineName,

                    style: const TextStyle(
                      fontSize: 18,

                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),

                PurchaseStatusBadge(status: purchase.status),
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

              purchase.quantity.toString(),
            ),

            _buildInfo(Icons.attach_money, "Price", "\$${purchase.price}"),

            const SizedBox(height: 14),

            if (purchase.status == "Pending")
              PurchaseActionButtons(purchase: purchase, onUpdate: onUpdate),
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
