import 'package:flutter/material.dart';

class InventoryFilterTabs extends StatelessWidget {
  final String selectedFilter;

  final Function(String) onChanged;

  const InventoryFilterTabs({
    super.key,
    required this.selectedFilter,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final filters = ["Out of Stock", "Expiring", "Low", "All"];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),

      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: filters.map((filter) {
          final isSelected = selectedFilter == filter;

          return GestureDetector(
            onTap: () => onChanged(filter),

            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),

              decoration: BoxDecoration(
                color: isSelected ? Colors.teal : Colors.grey.shade100,

                borderRadius: BorderRadius.circular(16),
              ),

              child: Text(
                filter,

                style: TextStyle(
                  fontWeight: FontWeight.w600,

                  color: isSelected ? Colors.white : Colors.blueGrey,
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
