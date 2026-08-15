import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

class MedicineActionButtons extends StatelessWidget {
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  const MedicineActionButtons({
    super.key,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        IconButton(
          onPressed: onEdit,
          icon: const Icon(
            Icons.edit,
            color: AppColors.pendingOrange,
          ),
        ),

        IconButton(
          onPressed: onDelete,
          icon: const Icon(
            Icons.delete,
            color: AppColors.errorRed,
          ),
        ),
      ],
    );
  }
}