import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';

enum EmployeeType { employee, trainee }

class EmployeeTypeSelectionWidget extends StatelessWidget {
  final EmployeeType? selectedType;
  final ValueChanged<EmployeeType> onChanged;

  const EmployeeTypeSelectionWidget({
    super.key,
    required this.selectedType,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Account Type',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: AppColors.darkGreen,
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _TypeCard(
                title: 'Employee',
                icon: Icons.work,
                isSelected: selectedType == EmployeeType.employee,
                onTap: () => onChanged(EmployeeType.employee),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _TypeCard(
                title: 'Trainee',
                icon: Icons.school,
                isSelected: selectedType == EmployeeType.trainee,
                onTap: () => onChanged(EmployeeType.trainee),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _TypeCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final bool isSelected;
  final VoidCallback onTap;

  const _TypeCard({
    required this.title,
    required this.icon,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.darkGreen : AppColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.darkGreen : Colors.grey.shade200,
          ),
        ),
        child: Column(
          children: [
            Icon(
              icon,
              size: 32,
              color: isSelected ? AppColors.white : AppColors.darkGreen,
            ),
            const SizedBox(height: 8),
            Text(
              title,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
                color: isSelected ? AppColors.white : AppColors.darkGreen,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
