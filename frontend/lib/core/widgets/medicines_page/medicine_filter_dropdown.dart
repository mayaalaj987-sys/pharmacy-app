import 'package:flutter/material.dart';

class MedicineFilterDropdown extends StatelessWidget {
  final String selectedCategory;
  final Function(String) onChanged;

  const MedicineFilterDropdown({
    super.key,
    required this.selectedCategory,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final categories = [
      "All",
      "Antibiotics",
      "Painkillers",
      "Vitamins",
      "Antidiabetics",
      "Gastrointestinal",
      "Respiratory",
      "Cardiovascular",
      "Dermatology",
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: DropdownButtonFormField<String>(
        key: ValueKey(selectedCategory),
        initialValue: selectedCategory,
        decoration: const InputDecoration(
          labelText: 'Filter inventory',
          prefixIcon: Icon(Icons.tune_rounded),
        ),
        items:
            [
              "All",
              "Low stock",
              "Out of stock",
              "Expiring soon",
              "Expired",
              ...categories.where((item) => item != 'All'),
            ].map((category) {
              return DropdownMenuItem(value: category, child: Text(category));
            }).toList(),

        onChanged: (value) {
          if (value != null) {
            onChanged(value);
          }
        },
      ),
    );
  }
}
