import 'package:flutter/material.dart';

import '../../../features/auth/data/models/medicine_model.dart';
import '../../data/medicine_data.dart';
import 'inventory_card.dart';

class InventoryList extends StatelessWidget {
  final String search;
  final String filter;

  const InventoryList({super.key, required this.search, required this.filter});

  @override
  Widget build(BuildContext context) {
    List<MedicineModel> filtered = medicines.where((medicine) {
      final quantity = int.tryParse(medicine.quantity) ?? 0;

      final reorderLevel = int.tryParse(medicine.reorderLevel) ?? 0;

      final matchesSearch = medicine.name.toLowerCase().contains(
        search.toLowerCase(),
      );

      if (!matchesSearch) {
        return false;
      }

      switch (filter) {
        case "Low":
          return quantity <= reorderLevel && quantity > 0;

        case "Out of Stock":
          return quantity == 0;

        case "Expiring":
          return true;

        default:
          return true;
      }
    }).toList();

    return ListView.builder(
      itemCount: filtered.length,

      itemBuilder: (context, index) {
        return InventoryCard(medicine: filtered[index]);
      },
    );
  }
}
