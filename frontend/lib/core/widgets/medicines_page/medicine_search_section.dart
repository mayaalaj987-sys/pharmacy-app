import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../custom_text_field.dart';

class MedicineSearchSection extends StatelessWidget {
  final TextEditingController controller;

  final VoidCallback? onAddMedicine;

  final ValueChanged<String>? onQueryChanged;

  const MedicineSearchSection({
    super.key,
    required this.controller,
    this.onAddMedicine,
    this.onQueryChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),

      child: Row(
        children: [
          if (onAddMedicine != null)
            GestureDetector(
              onTap: onAddMedicine,

              child: Container(
                width: 50,
                height: 50,

                decoration: BoxDecoration(
                  color: AppColors.darkGreen,
                  borderRadius: BorderRadius.circular(16),
                ),

                child: const Icon(Icons.add, color: Colors.white),
              ),
            ),

          const SizedBox(width: 10),

          Expanded(
            child: CustomTextField(
              controller: controller,
              hint: "Search medicine...",
              prefixIcon: Icons.search,
              onChanged: onQueryChanged,
            ),
          ),
        ],
      ),
    );
  }
}
