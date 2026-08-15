import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

import '../custom_text_field.dart';

class MedicineSearchSection extends StatelessWidget {
  final TextEditingController controller;

  final VoidCallback onAddMedicine;

  const MedicineSearchSection({
    super.key,
    required this.controller,
    required this.onAddMedicine,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: 16,
      ),

      child: Row(
        children: [
          GestureDetector(
            onTap: onAddMedicine,

            child: Container(
              width: 50,
              height: 50,

              decoration: BoxDecoration(
                color: AppColors.darkGreen,
                borderRadius: BorderRadius.circular(16),
              ),

              child: const Icon(
                Icons.add,
                color: Colors.white,
              ),
            ),
          ),

          const SizedBox(width: 10),

          Container(
            width: 50,
            height: 50,

            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade300),
            ),

            child: const Icon(Icons.qr_code),
          ),

          const SizedBox(width: 10),

          Expanded(
            child: CustomTextField(
              controller: controller,
              hint: "Search medicine...",
              prefixIcon: Icons.search,
            ),
          ),
        ],
      ),
    );
  }
}