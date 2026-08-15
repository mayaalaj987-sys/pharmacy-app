import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/sign_employee/employee_file_upload_widget.dart';
import '../../../../core/widgets/sign_employee/employee_info_form_widget.dart';
import '../../../../core/widgets/sign_employee/employee_type_selection_widget.dart';
import 'employee_pending_page.dart';

class EmployeeSignupPage extends StatefulWidget {
  const EmployeeSignupPage({super.key});

  @override
  State<EmployeeSignupPage> createState() => _EmployeeSignupPageState();
}

class _EmployeeSignupPageState extends State<EmployeeSignupPage> {
  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final locationController = TextEditingController();

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

  void signUp() {
    if (nameController.text.isEmpty ||
        phoneController.text.isEmpty ||
        locationController.text.isEmpty) {
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

    // TODO: connect to API
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const EmployeePendingPage()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
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
                style: TextStyle(fontSize: 15, color: AppColors.secondaryText),
              ),
              const SizedBox(height: 32),
              EmployeeInfoFormWidget(
                nameController: nameController,
                phoneController: phoneController,
                locationController: locationController,
              ),
              const SizedBox(height: 24),
              EmployeeFileUploadWidget(
                title: 'Upload CV',
                subtitle: 'PDF accepted',
                icon: Icons.description,
                file: cvFile,
                onTap: () => pickFile((file) => setState(() => cvFile = file)),
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
              CustomButton(text: 'Sign Up', onPressed: signUp),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}
