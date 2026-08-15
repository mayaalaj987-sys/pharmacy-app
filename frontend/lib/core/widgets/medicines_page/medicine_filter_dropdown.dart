import 'package:flutter/material.dart';
import '../../theme/app_colors.dart';

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
      "Gastrointestinal",
      "Respiratory",
      "Cardiovascular",
      "Dermatology",
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: 16,
        vertical: 10,
      ),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: AppColors.lightGreen,
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: DropdownButtonFormField<String>(
          value: selectedCategory,

          decoration: const InputDecoration(
            border: InputBorder.none,
            contentPadding: EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 14,
            ),
          ),

          icon: const Icon(
            Icons.keyboard_arrow_down_rounded,
            color: AppColors.darkGreen,
          ),

          dropdownColor: Colors.white,

          style: const TextStyle(
            color: AppColors.darkGreen,
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),

          items: categories.map((category) {
            return DropdownMenuItem(
              value: category,
              child: Text(category),
            );
          }).toList(),

          onChanged: (value) {
            if (value != null) {
              onChanged(value);
            }
          },
        ),
      ),
    );
  }
}