import 'package:flutter/material.dart';

import '../../../features/inventory/domain/medicine.dart';
import '../../format/money.dart';

class PosSearchResults extends StatelessWidget {
  final List<Medicine> medicines;
  final Function(Medicine) onAdd;

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
        color: Theme.of(context).cardColor,

        borderRadius: BorderRadius.circular(16),

        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
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
                  money(medicine.sellingPrice),
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
