import 'dart:io';
import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/sign_employee/employee_file_upload_widget.dart';
import '../../../../core/widgets/sign_employee/employee_info_form_widget.dart';
import '../../../../core/widgets/sign_employee/employee_type_selection_widget.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import 'employee_pending_page.dart';

class EmployeeSignupPage extends StatefulWidget {
  const EmployeeSignupPage({super.key});

  @override
  State<EmployeeSignupPage> createState() => _EmployeeSignupPageState();
}

class _EmployeeSignupPageState extends State<EmployeeSignupPage> {
  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final emailController = TextEditingController();
  final passwordController = TextEditingController();

  EmployeeType? selectedType;
  File? cvFile;
  File? certificateFile;

  Future<void> pickFile(Function(File) onPicked) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
    );

    if (result != null && result.files.single.path != null) {
      onPicked(File(result.files.single.path!));
    }
  }

  Future<void> signUp() async {
    if (nameController.text.isEmpty ||
        phoneController.text.isEmpty ||
        emailController.text.isEmpty ||
        passwordController.text.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Please fill all fields')));
      return;
    }

    if (selectedType == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select account type')),
      );
      return;
    }

    if (cvFile == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Please upload your CV')));
      return;
    }

    if (selectedType == EmployeeType.employee && certificateFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Employees must upload a certificate')),
      );
      return;
    }

    final data = FormData.fromMap({
      'name': nameController.text.trim(),
      'phone': phoneController.text.trim(),
      'email': emailController.text.trim(),
      'password': passwordController.text,
      'role': selectedType!.name,
      'cv': await MultipartFile.fromFile(cvFile!.path),
      if (certificateFile != null)
        'experience_proof': await MultipartFile.fromFile(certificateFile!.path),
    });

    if (mounted) {
      context.read<AuthCubit>().registerEmployee(data);
    }
  }

  @override
  void dispose() {
    nameController.dispose();
    phoneController.dispose();
    emailController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is EmployeeRegisterSuccess) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const EmployeePendingPage()),
          );
        } else if (state is AuthError) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(state.message)));
        }
      },
      builder: (context, state) {
        return Scaffold(
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Create Account',
                    style: TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Fill in your details to get started',
                    style: TextStyle(
                      fontSize: 15,
                      color: AppColors.secondaryText,
                    ),
                  ),
                  const SizedBox(height: 32),
                  EmployeeInfoFormWidget(
                    nameController: nameController,
                    phoneController: phoneController,
                    emailController: emailController,
                    passwordController: passwordController,
                  ),
                  const SizedBox(height: 24),
                  EmployeeFileUploadWidget(
                    title: 'Upload CV',
                    subtitle: 'PDF accepted',
                    icon: Icons.description,
                    file: cvFile,
                    onTap: () =>
                        pickFile((file) => setState(() => cvFile = file)),
                  ),
                  const SizedBox(height: 24),
                  EmployeeTypeSelectionWidget(
                    selectedType: selectedType,
                    onChanged: (type) => setState(() => selectedType = type),
                  ),
                  if (selectedType == EmployeeType.employee) ...[
                    const SizedBox(height: 24),
                    EmployeeFileUploadWidget(
                      title: 'Upload Certificate',
                      subtitle: 'Training certificate required for employees',
                      icon: Icons.workspace_premium,
                      file: certificateFile,
                      onTap: () => pickFile(
                        (file) => setState(() => certificateFile = file),
                      ),
                    ),
                  ],
                  const SizedBox(height: 40),
                  CustomButton(
                    text: state is AuthLoading ? 'Loading...' : 'Sign Up',
                    onPressed: state is AuthLoading ? null : signUp,
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
