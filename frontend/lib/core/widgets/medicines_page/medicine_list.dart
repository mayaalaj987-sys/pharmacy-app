import 'package:flutter/material.dart';

import '../../data/medicine_data.dart';
import 'medicine_card.dart';

class MedicineList extends StatefulWidget {

  final String selectedCategory;

  const MedicineList({
    super.key,
    required this.selectedCategory,
  });

  @override
  State<MedicineList> createState() =>
      _MedicineListState();
}

class _MedicineListState
    extends State<MedicineList> {

  @override
  Widget build(BuildContext context) {

    final filteredMedicines =
    widget.selectedCategory == "All"
        ? medicines
        : medicines.where((medicine) {

      return medicine.category ==
          widget.selectedCategory;

    }).toList();

    if (filteredMedicines.isEmpty) {
      return const Center(
        child: Text(
          "No Medicines Found",
        ),
      );
    }

    return ListView.builder(
      itemCount: filteredMedicines.length,

      itemBuilder: (context, index) {

        final medicine =
        filteredMedicines[index];

        final originalIndex =
        medicines.indexOf(medicine);


        return MedicineCard(
          medicine: medicine,
          index: originalIndex,

          onRefresh: () {
            setState(() {});
          },
        );
      },
    );
  }
}