import 'package:flutter/material.dart';

import '../../data/medicine_data.dart';

class InventoryStatsSection extends StatelessWidget {
  const InventoryStatsSection({super.key});

  @override
  Widget build(BuildContext context) {

    /// Total
    final totalMedicines = medicines.length;

    /// Out Of Stock
    final outOfStock = medicines.where((medicine) {

      final quantity =
          int.tryParse(medicine.quantity) ?? 0;

      return quantity == 0;

    }).length;

    /// Expiring
    final expiring = medicines.where((medicine) {

      if (medicine.expiryDate.isEmpty) {
        return false;
      }

      try {

        final expiryDate =
        DateTime.parse(medicine.expiryDate);

        final days =
            expiryDate.difference(
              DateTime.now(),
            ).inDays;

        return days <= 90;

      } catch (e) {
        return false;
      }

    }).length;

    return Padding(
      padding: const EdgeInsets.all(16),

      child: Row(
        children: [

          Expanded(
            child: _statCard(
              title: "Expiring",
              value: expiring.toString(),
              color: Colors.amber,
              background: const Color(0xffFFF8E8),
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: _statCard(
              title: "Out of Stock",
              value: outOfStock.toString(),
              color: Colors.red,
              background: const Color(0xffFFF0F0),
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: _statCard(
              title: "Total",
              value: totalMedicines.toString(),
              color: Colors.teal,
              background: const Color(0xffEEF8F7),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statCard({
    required String title,
    required String value,
    required Color color,
    required Color background,
  }) {
    return Container(
      height: 100,

      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(24),
      ),

      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [

          Text(
            value,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),

          const SizedBox(height: 8),

          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 16,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}