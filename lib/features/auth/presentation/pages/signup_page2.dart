import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:file_picker/file_picker.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/widgets/custom_upload_card.dart';

import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import 'pending_page.dart';

class SignupPage2 extends StatefulWidget {
  final int pharmacistId;

  const SignupPage2({super.key, required this.pharmacistId});

  @override
  State<SignupPage2> createState() => _SignupPage2State();
}

class _SignupPage2State extends State<SignupPage2> {
  final pharmacyNameController = TextEditingController();
  final pharmacyAddressController = TextEditingController();

  File? certificateFile;
  File? licenseFile;

  Future<void> pickCertificate() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ["jpg", "jpeg", "png", "pdf"],
    );

    if (result != null && result.files.single.path != null) {
      setState(() {
        certificateFile = File(result.files.single.path!);
      });
    }
  }

  Future<void> pickLicense() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ["jpg", "jpeg", "png", "pdf"],
    );

    if (result != null && result.files.single.path != null) {
      setState(() {
        licenseFile = File(result.files.single.path!);
      });
    }
  }

  void register() async {
    if (pharmacyNameController.text.isEmpty ||
        pharmacyAddressController.text.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text("Please fill all fields")));
      return;
    }

    if (certificateFile == null || licenseFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text("Please upload both certificate and license"),
        ),
      );
      return;
    }

    FormData data = FormData.fromMap({
      "pharmacist_id": widget.pharmacistId,
      "pharmacy_name": pharmacyNameController.text,
      "pharmacy_address": pharmacyAddressController.text,
      "certificate": await MultipartFile.fromFile(certificateFile!.path),
      "license": await MultipartFile.fromFile(licenseFile!.path),
    });

    context.read<AuthCubit>().registerPharmacy(data);
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is AuthSuccess) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const PendingPage()),
          );
        }

        if (state is AuthError) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(state.message)));
        }
      },
      builder: (context, state) {
        return Scaffold(
          backgroundColor: AppColors.veryLightGreen,
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  CustomTextField(
                    controller: pharmacyNameController,
                    hint: "Pharmacy Name",
                    prefixIcon: Icons.local_pharmacy,
                  ),
                  const SizedBox(height: 20),
                  CustomTextField(
                    controller: pharmacyAddressController,
                    hint: "Pharmacy Address",
                    prefixIcon: Icons.location_on,
                  ),
                  const SizedBox(height: 30),
                  CustomUploadCard(
                    onTap: pickCertificate,
                    title: "Upload Certificate",
                    uploaded: certificateFile != null,
                  ),
                  const SizedBox(height: 16),
                  CustomUploadCard(
                    onTap: pickLicense,
                    title: "Upload License",
                    uploaded: licenseFile != null,
                  ),
                  const SizedBox(height: 40),
                  CustomButton(
                    text: state is AuthLoading ? "Loading..." : "Finish Signup",
                    onPressed: state is AuthLoading ? null : register,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
