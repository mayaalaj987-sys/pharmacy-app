import 'package:flutter/material.dart';

import '../../../features/auth/data/models/medicine_model.dart';

class InventoryCard extends StatelessWidget {

  final MedicineModel medicine;

  const InventoryCard({
    super.key,
    required this.medicine,
  });


  @override
  Widget build(BuildContext context) {

    return Card(
      margin: const EdgeInsets.symmetric(
        horizontal: 16,
        vertical: 8,
      ),

      child: Padding(
        padding: const EdgeInsets.all(16),

        child: Column(
          crossAxisAlignment:
          CrossAxisAlignment.start,

          children: [

            Text(
              medicine.name,

              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),

            const SizedBox(height: 8),

            Text(
              medicine.category,
            ),

            const SizedBox(height: 8),

            Text(
              "Quantity: ${medicine.quantity}",
            ),

            Text(
              "Expiry: ${medicine.expiryDate}",
            ),
          ],
        ),
      ),
    );
  }
}