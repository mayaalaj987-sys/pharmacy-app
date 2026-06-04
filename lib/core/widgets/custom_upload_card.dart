import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class CustomUploadCard extends StatelessWidget {
  final VoidCallback onTap;

  final String title;

  final bool uploaded;

  const CustomUploadCard({
    super.key,
    required this.onTap,
    required this.title,
    required this.uploaded,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,

      child: Container(
        width: double.infinity,

        padding: const EdgeInsets.all(22),

        decoration: BoxDecoration(
          color: AppColors.white,

          borderRadius: BorderRadius.circular(20),

          border: Border.all(
            color: uploaded ? AppColors.successGreen : AppColors.borderColor,
          ),
        ),

        child: Column(
          children: [
            Icon(
              uploaded ? Icons.check_circle : Icons.upload_file,

              size: 42,

              color: uploaded ? AppColors.successGreen : AppColors.tealGreen,
            ),

            const SizedBox(height: 12),

            Text(
              title,

              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
            ),

            if (uploaded)
              const Padding(
                padding: EdgeInsets.only(top: 6),

                child: Text(
                  'Uploaded',

                  style: TextStyle(
                    color: AppColors.successGreen,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
