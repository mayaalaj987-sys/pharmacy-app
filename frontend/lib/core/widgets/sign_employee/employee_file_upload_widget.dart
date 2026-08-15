import 'dart:io';
import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';

class EmployeeFileUploadWidget extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final File? file;
  final VoidCallback onTap;

  const EmployeeFileUploadWidget({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.file,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bool isUploaded = file != null;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isUploaded ? AppColors.darkGreen : Colors.grey.shade200,
            width: isUploaded ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: isUploaded
                    ? AppColors.darkGreen
                    : AppColors.veryLightGreen,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                isUploaded ? Icons.check : icon,
                color: isUploaded ? AppColors.white : AppColors.darkGreen,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    isUploaded ? 'File uploaded successfully' : subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: isUploaded
                          ? AppColors.darkGreen
                          : AppColors.secondaryText,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              isUploaded ? Icons.edit : Icons.upload,
              color: AppColors.darkGreen,
            ),
          ],
        ),
      ),
    );
  }
}
