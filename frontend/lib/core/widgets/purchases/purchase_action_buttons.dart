import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';
import '../../../../core/data/medicine_data.dart';
import '../../../features/auth/data/models/medicine_model.dart';


class PurchaseActionButtons extends StatelessWidget {
  final dynamic purchase;

  final VoidCallback onUpdate;

  const PurchaseActionButtons({
    super.key,

    required this.purchase,

    required this.onUpdate,
  });

  void receive() {
    purchase.status = "Received";

    final index = medicines.indexWhere(
      (m) =>
          m.name.trim().toLowerCase() ==
          purchase.medicineName.trim().toLowerCase(),
    );

    if (index != -1) {
      final old = medicines[index];

      medicines[index] = MedicineModel(
        name: old.name,

        category: old.category,

        manufacturer: old.manufacturer,

        sellingPrice: old.sellingPrice,

        costPrice: old.costPrice,

        quantity: (int.parse(old.quantity) + purchase.quantity).toString(),

        reorderLevel: old.reorderLevel,

        expiryDate: old.expiryDate,

        barcode: old.barcode,

        notes: old.notes,
      );
    }

    onUpdate();
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: ElevatedButton.icon(
            onPressed: receive,

            icon: const Icon(Icons.check),

            label: const Text("Receive"),
          ),
        ),

        const SizedBox(width: 10),

        Expanded(
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.errorRed),

            onPressed: () {
              purchase.status = "Cancelled";

              onUpdate();
            },

            icon: const Icon(Icons.close),

            label: const Text("Cancel"),
          ),
        ),
      ],
    );
  }
}
