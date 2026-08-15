import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';
import '../../../features/auth/presentation/pages/add_medicine_page.dart';
import '../../data/medicine_data.dart';
import '../../../features/auth/data/models/medicine_model.dart';
import 'medicine_action_buttons.dart';

class MedicineCard extends StatelessWidget {
  final MedicineModel medicine;
  final int index;
  final VoidCallback onRefresh;

  const MedicineCard({
    super.key,
    required this.medicine,
    required this.index,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.veryLightGreen,
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),

      child: Padding(
        padding: const EdgeInsets.all(16),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Row(
              children: [
                const SizedBox(width: 12),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Text(
                        medicine.name,
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: AppColors.darkGreen,
                        ),
                      ),

                      Text(
                        medicine.category,
                        style: TextStyle(color: AppColors.grey),
                      ),
                    ],
                  ),
                ),

                MedicineActionButtons(
                  onEdit: () async {

                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => AddMedicinePage(
                          medicine: medicine,
                          index: index,
                        ),
                      ),
                    );

                    onRefresh();
                  },
                  onDelete: () {
                    medicines.removeAt(index);

                    onRefresh();
                  },
                ),
              ],
            ),

            const Divider(height: 25),

            Row(
              children: [
                Expanded(child: infoTile("Quantity", medicine.quantity)),

                Expanded(child: infoTile("Expiry", medicine.expiryDate)),
              ],
            ),

            const SizedBox(height: 10),

            Row(
              children: [
                Expanded(child: infoTile("Selling ", medicine.sellingPrice)),

                Expanded(child: infoTile("Cost", medicine.costPrice)),
              ],
            ),

            const SizedBox(height: 10),

            infoTile("Barcode", medicine.barcode),

            if (medicine.notes.isNotEmpty) ...[
              const SizedBox(height: 10),

              infoTile("Notes", medicine.notes),
            ],
          ],
        ),
      ),
    );
  }

  Widget infoTile(String title, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            color: AppColors.darkGreen,
          ),
        ),

        const SizedBox(height: 3),

        Text(value),
      ],
    );
  }
}
