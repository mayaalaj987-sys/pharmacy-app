import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

class MedicineActionButtons extends StatelessWidget {
  final VoidCallback onEdit;

  /// Optional: the backend exposes no medicine-delete contract, so callers that
  /// cannot delete simply omit this and no delete affordance is rendered.
  final VoidCallback? onDelete;

  const MedicineActionButtons({
    super.key,
    required this.onEdit,
    this.onDelete,
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

        if (onDelete != null)
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