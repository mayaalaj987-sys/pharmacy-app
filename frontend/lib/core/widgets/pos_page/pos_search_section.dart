import 'package:flutter/material.dart';
import '../../../../../core/widgets/custom_text_field.dart';

class PosSearchSection extends StatelessWidget {
  final TextEditingController controller;

  const PosSearchSection({super.key, required this.controller});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),

      child: Row(
        children: [
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
