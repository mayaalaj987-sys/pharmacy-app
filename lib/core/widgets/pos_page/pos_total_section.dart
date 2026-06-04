import 'package:flutter/material.dart';
import '../../../../../core/theme/app_colors.dart';
import '../../../../../core/widgets/custom_button.dart';

class PosTotalSection extends StatelessWidget {
  const PosTotalSection({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            const Text(
              '\$0.00',
              style: TextStyle(
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
          text: "Complete Sale - \$0.00",
          onPressed: () {},
        ),
      ],
    );
  }
}