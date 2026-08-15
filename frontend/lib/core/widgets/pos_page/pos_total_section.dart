import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';
import '../custom_button.dart';

class PosTotalSection extends StatelessWidget {
  final double total;
  final VoidCallback onCompleteSale;

  const PosTotalSection({
    super.key,
    required this.total,
    required this.onCompleteSale,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Text(
              "\$${total.toStringAsFixed(2)}",
              style: const TextStyle(
                fontSize: 36,
                fontWeight: FontWeight.bold,
                color: AppColors.lightGreen,
              ),
            ),

            const Spacer(),

            Text(
              'Total',
              style: TextStyle(
                fontSize: 22,
                color: Colors.grey.shade600,
              ),
            ),
          ],
        ),

        const SizedBox(height: 20),

        CustomButton(
          text: "Complete Sale - \$${total.toStringAsFixed(2)}",
          onPressed: onCompleteSale,
        ),
      ],
    );
  }
}