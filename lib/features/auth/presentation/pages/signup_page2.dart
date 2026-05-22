import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/widgets/custom_upload_card.dart';

class SignupPage2 extends StatefulWidget {
  final String username;
  final String email;
  final String password;

  final File? profileImage;

  const SignupPage2({
    super.key,

    required this.username,
    required this.email,
    required this.password,
    required this.profileImage,
  });

  @override
  State<SignupPage2> createState() => _SignupPage2State();
}

class _SignupPage2State extends State<SignupPage2> {
  final pharmacyNameController = TextEditingController();

  final pharmacyLocationController = TextEditingController();

  List<File> pharmacyDocuments = [];

  Future<void> pickDocuments() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.image,
    );

    if (result != null) {
      if (result.files.length != 2) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please select exactly 2 images')),
        );

        return;
      }

      setState(() {
        pharmacyDocuments = result.paths.map((path) => File(path!)).toList();
      });
    }
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
              const SizedBox(height: 20),

              const Text(
                'Pharmacy Details',

                style: TextStyle(
                  fontSize: 30,

                  fontWeight: FontWeight.bold,

                  color: AppColors.darkGreen,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Complete your pharmacy information',

                style: TextStyle(fontSize: 15, color: AppColors.secondaryText),
              ),

              const SizedBox(height: 40),

              Container(
                padding: const EdgeInsets.all(14),

                decoration: BoxDecoration(
                  color: AppColors.white,

                  borderRadius: BorderRadius.circular(18),
                ),

                child: Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 10,

                        decoration: BoxDecoration(
                          color: AppColors.lightGreen,

                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),

                    const SizedBox(width: 8),

                    Expanded(
                      child: Container(
                        height: 10,

                        decoration: BoxDecoration(
                          color: AppColors.lightGreen,

                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 40),

              CustomTextField(
                controller: pharmacyNameController,

                hint: 'Pharmacy Name',

                prefixIcon: Icons.local_pharmacy,
              ),

              const SizedBox(height: 20),

              CustomTextField(
                controller: pharmacyLocationController,

                hint: 'Pharmacy Location',

                prefixIcon: Icons.location_on,
              ),

              const SizedBox(height: 30),

              CustomUploadCard(
                onTap: pickDocuments,

                title: 'Upload Pharmacy Documents',

                uploaded: pharmacyDocuments.isNotEmpty,
              ),

              const SizedBox(height: 12),

              Text(
                pharmacyDocuments.isEmpty
                    ? 'Please upload 2 verification images'
                    : '${pharmacyDocuments.length} files selected',

                style: const TextStyle(color: AppColors.secondaryText),
              ),

              const SizedBox(height: 40),

              CustomButton(
                text: 'Finish Signup',

                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      backgroundColor: AppColors.pendingOrange,

                      content: Text('Account sent for review'),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
