import 'package:flutter/material.dart';

import '../../../features/auth/data/models/medicine_model.dart';

class InventoryCard extends StatelessWidget {
  final MedicineModel medicine;

  const InventoryCard({super.key, required this.medicine});

  @override
  Widget build(BuildContext context) {
    final quantity = int.tryParse(medicine.quantity) ?? 0;
    final reorder = int.tryParse(medicine.reorderLevel) ?? 10;

    final isLowStock = quantity <= reorder;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),

      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(.05),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),

      child: Padding(
        padding: const EdgeInsets.all(16),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Text(
                        medicine.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),

                      const SizedBox(height: 4),

                      Text(
                        medicine.manufacturer,
                        style: TextStyle(color: Colors.grey.shade600),
                      ),
                    ],
                  ),
                ),

                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),

                  decoration: BoxDecoration(
                    color: Colors.teal.withOpacity(.1),
                    borderRadius: BorderRadius.circular(20),
                  ),

                  child: Text(
                    medicine.category,
                    style: const TextStyle(
                      color: Colors.teal,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 16),

            Row(
              children: [
                Expanded(
                  child: _infoCard(
                    "Price",
                    "\$${medicine.sellingPrice}",
                    Colors.green,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: _infoCard(
                    "Stock",
                    medicine.quantity,
                    isLowStock ? Colors.red : Colors.blue,
                  ),
                ),

                const SizedBox(width: 10),

                Expanded(
                  child: _infoCard(
                    "Expiry",
                    medicine.expiryDate,
                    Colors.orange,
                  ),
                ),
              ],
            ),

            if (isLowStock) ...[
              const SizedBox(height: 12),

              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(10),

                decoration: BoxDecoration(
                  color: Colors.red.withOpacity(.1),
                  borderRadius: BorderRadius.circular(12),
                ),

                child: const Text(
                  "Low Stock",
                  style: TextStyle(
                    color: Colors.red,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _infoCard(String title, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(10),

      decoration: BoxDecoration(
        color: color.withOpacity(.1),
        borderRadius: BorderRadius.circular(12),
      ),

      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(color: color, fontWeight: FontWeight.bold),
          ),

          const SizedBox(height: 4),

          Text(
            title,
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
          ),
        ],
      ),
    );
  }
}
