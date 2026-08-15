import 'package:flutter/material.dart';
import '../../../../../core/widgets/custom_text_field.dart';

class PosSearchSection extends StatelessWidget {
  final TextEditingController controller;

  const PosSearchSection({
    super.key,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),

      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,

            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade300),
            ),

            child: const Icon(Icons.qr_code_scanner),
          ),

          const SizedBox(width: 12),

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