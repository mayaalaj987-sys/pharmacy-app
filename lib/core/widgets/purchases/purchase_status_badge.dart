import 'package:flutter/material.dart';

import '../../theme/app_colors.dart';

class PurchaseStatusBadge extends StatelessWidget {
  final String status;

  const PurchaseStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;

    IconData icon;

    switch (status) {
      case "Received":
        color = AppColors.lightGreen;

        icon = Icons.check_circle_outline;

        break;

      case "Cancelled":
        color = AppColors.errorRed;

        icon = Icons.cancel_outlined;

        break;

      default:
        color = AppColors.pendingOrange;

        icon = Icons.access_time;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),

      decoration: BoxDecoration(
        color: color.withOpacity(.12),

        borderRadius: BorderRadius.circular(30),
      ),

      child: Row(
        mainAxisSize: MainAxisSize.min,

        children: [
          Icon(icon, size: 15, color: color),

          const SizedBox(width: 5),

          Text(
            status,

            style: TextStyle(
              color: color,

              fontWeight: FontWeight.w600,

              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
