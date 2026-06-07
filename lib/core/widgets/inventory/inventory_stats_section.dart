import 'package:flutter/material.dart';

class InventoryStatsSection extends StatelessWidget {
  const InventoryStatsSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),

      child: Row(
        children: [
          Expanded(
            child: _statCard(
              title: "Expiring",
              value: "8",
              color: Colors.amber,
              background: const Color(0xffFFF8E8),
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: _statCard(
              title: "Out of Stock",
              value: "0",
              color: Colors.red,
              background: const Color(0xffFFF0F0),
            ),
          ),

          const SizedBox(width: 12),

          Expanded(
            child: _statCard(
              title: "Total",
              value: "11",
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

            style: TextStyle(fontSize: 16, color: color),
          ),
        ],
      ),
    );
  }
}
