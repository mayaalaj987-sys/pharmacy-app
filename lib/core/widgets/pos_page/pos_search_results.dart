import 'package:flutter/material.dart';

import '../../../features/auth/data/models/medicine_model.dart';

class PosSearchResults extends StatelessWidget {
  final List<MedicineModel> medicines;
  final Function(MedicineModel) onAdd;

  const PosSearchResults({
    super.key,
    required this.medicines,
    required this.onAdd,
  });

  @override
  Widget build(BuildContext context) {
    if (medicines.isEmpty) {
      return const SizedBox();
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),

      decoration: BoxDecoration(
        color: Colors.white,

        borderRadius: BorderRadius.circular(16),

        boxShadow: const [BoxShadow(blurRadius: 10, color: Colors.black12)],
      ),

      child: ListView.builder(
        shrinkWrap: true,

        physics: const NeverScrollableScrollPhysics(),

        itemCount: medicines.length,

        itemBuilder: (context, index) {
          final medicine = medicines[index];

          return ListTile(
            title: Text(medicine.name),

            subtitle: Text("Stock: ${medicine.quantity}"),

            trailing: Row(
              mainAxisSize: MainAxisSize.min,

              children: [
                Text(
                  "\$${medicine.sellingPrice}",
                  style: const TextStyle(
                    color: Colors.green,
                    fontWeight: FontWeight.bold,
                  ),
                ),

                IconButton(
                  onPressed: () {
                    onAdd(medicine);
                  },

                  icon: const Icon(Icons.add_circle, color: Colors.green),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
