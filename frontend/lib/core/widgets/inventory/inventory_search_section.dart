import 'package:flutter/material.dart';

class InventorySearchSection extends StatelessWidget {

  final TextEditingController controller;

  const InventorySearchSection({
    super.key,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: 16,
      ),

      child: Container(
        height: 55,

        decoration: BoxDecoration(
          color: Colors.white,

          borderRadius: BorderRadius.circular(18),

          border: Border.all(
            color: Colors.grey.shade300,
          ),
        ),

        child: TextField(
          controller: controller,

          decoration: InputDecoration(
            hintText: "Search inventory...",

            prefixIcon: const Icon(
              Icons.search,
            ),

            border: InputBorder.none,

            contentPadding:
            const EdgeInsets.symmetric(
              vertical: 16,
            ),
          ),
        ),
      ),
    );
  }
}