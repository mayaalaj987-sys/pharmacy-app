import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_text_field.dart';

class EmployeeInfoFormWidget extends StatelessWidget {
  final TextEditingController nameController;
  final TextEditingController phoneController;
  final TextEditingController locationController;

  const EmployeeInfoFormWidget({
    super.key,
    required this.nameController,
    required this.phoneController,
    required this.locationController,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        CustomTextField(
          controller: nameController,
          hint: 'Full Name',
          prefixIcon: Icons.person,
        ),
        const SizedBox(height: 16),
        CustomTextField(
          controller: phoneController,
          hint: 'Phone Number',
          prefixIcon: Icons.phone,
          keyboardType: TextInputType.phone,
        ),
        const SizedBox(height: 16),
        CustomTextField(
          controller: locationController,
          hint: 'Location',
          prefixIcon: Icons.location_on,
        ),
      ],
    );
  }
}
