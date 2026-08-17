import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';
import '../../../features/auth/presentation/pages/add_medicine_page.dart';
import '../../../features/inventory/domain/medicine.dart';
import 'medicine_action_buttons.dart';

class MedicineCard extends StatelessWidget {
  final Medicine medicine;
  final VoidCallback onRefresh;

  /// Employees may read stock but cannot add or edit medicines
  /// (backend exposes /medicines/add and /medicines/edit to pharmacists only).
  final bool readOnly;

  const MedicineCard({
    super.key,
    required this.medicine,
    required this.onRefresh,
    this.readOnly = false,
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

                // No delete: the backend exposes no medicine-delete contract,
                // and medicines are referenced by sale history.
                MedicineActionButtons(
                  onEdit: readOnly
                      ? null
                      : () async {
                          await Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) =>
                                  AddMedicinePage(medicine: medicine),
                            ),
                          );

                          onRefresh();
                        },
                ),
              ],
            ),

            const Divider(height: 25),

            Row(
              children: [
                Expanded(
                  child: infoTile("Quantity", medicine.quantity.toString()),
                ),

                Expanded(child: infoTile("Expiry", _expiry)),
              ],
            ),

            const SizedBox(height: 10),

            Row(
              children: [
                Expanded(
                  child: infoTile(
                    "Selling ",
                    medicine.sellingPrice.toStringAsFixed(2),
                  ),
                ),

                Expanded(
                  child: infoTile(
                    "Cost",
                    medicine.costPrice.toStringAsFixed(2),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String get _expiry {
    final date = medicine.expireDate;
    if (date == null) return '-';
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
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
