import 'package:flutter/material.dart';
import '../../../../core/widgets/custom_text_field.dart';

class EmployeeInfoFormWidget extends StatelessWidget {
  final TextEditingController nameController;
  final TextEditingController phoneController;
  final TextEditingController emailController;
  final TextEditingController passwordController;

  const EmployeeInfoFormWidget({
    super.key,
    required this.nameController,
    required this.phoneController,
    required this.emailController,
    required this.passwordController,
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
          controller: emailController,
          hint: 'Email',
          prefixIcon: Icons.email,
          keyboardType: TextInputType.emailAddress,
        ),
        const SizedBox(height: 16),
        CustomTextField(
          controller: passwordController,
          hint: 'Password',
          prefixIcon: Icons.lock,
          isPassword: true,
        ),
      ],
    );
  }
}
