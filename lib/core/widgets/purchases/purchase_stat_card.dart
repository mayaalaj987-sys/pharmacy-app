import 'package:flutter/material.dart';

class PurchaseStatCard extends StatelessWidget {
  final String title;

  final String value;

  final Color color;

  const PurchaseStatCard({
    super.key,

    required this.title,

    required this.value,

    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),

      decoration: BoxDecoration(
        color: color.withOpacity(.15),

        borderRadius: BorderRadius.circular(16),
      ),

      child: Column(
        children: [
          Text(
            value,

            style: TextStyle(
              fontSize: 22,

              fontWeight: FontWeight.bold,

              color: color,
            ),
          ),

          const SizedBox(height: 6),

          Text(title),
        ],
      ),
    );
  }
}
