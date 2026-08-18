import 'dart:io';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/widgets/custom_upload_card.dart';
import '../../../account/domain/pharmacy_location_draft.dart';
import '../cubit/auth_cubit.dart';
import '../widgets/pharmacy_location_field.dart';

/// Registers an additional pharmacy for the signed-in owner.
///
/// Mirrors the pharmacy half of the signup flow, but posts to
/// `POST /pharmacy/add`. Only the fields `AddPharmacyRequest` accepts are
/// sent — the owner comes from the bearer token and the backend forces
/// `pending`, so `pharmacist_id`, `owner_id` and `status` are never submitted.
/// Coordinates are sent only as a complete pair, which is the one shape the
/// backend accepts.
///
/// The busy flag is local rather than in [AuthCubit] because this is a pushed
/// route: driving it from the auth state would rebuild the shell underneath.
class AddPharmacyPage extends StatefulWidget {
  const AddPharmacyPage({super.key});

  @override
  State<AddPharmacyPage> createState() => _AddPharmacyPageState();
}

class _AddPharmacyPageState extends State<AddPharmacyPage> {
  final _nameController = TextEditingController();
  final _addressController = TextEditingController();

  File? _certificate;
  File? _license;
  double? _latitude;
  double? _longitude;
  String? _suggestedAddress;
  bool _submitting = false;
  bool _submitted = false;

  @override
  void dispose() {
    _nameController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _pick(void Function(File) assign) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
    );

    final path = result?.files.single.path;
    if (path == null) return;

    setState(() => assign(File(path)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: 'Add Pharmacy', showNotificationBell: false),
      ),
      body: SafeArea(child: _submitted ? _successView() : _formView()),
    );
  }

  Widget _formView() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Register another pharmacy under your account. An administrator '
            'reviews it before it becomes available.',
            style: TextStyle(color: AppColors.darkGreen),
          ),
          const SizedBox(height: 24),
          CustomTextField(
            key: const ValueKey('add-pharmacy-name-field'),
            controller: _nameController,
            hint: 'Pharmacy Name',
            prefixIcon: Icons.local_pharmacy,
          ),
          const SizedBox(height: 20),
          PharmacyLocationField(
            key: const ValueKey('add-pharmacy-location-field'),
            addressController: _addressController,
            latitude: _latitude,
            longitude: _longitude,
            suggestedAddress: _suggestedAddress,
            enabled: !_submitting,
            onLocationPicked: _applyLocation,
            onLocationCleared: _clearLocation,
            onSuggestionAccepted: _useSuggestedAddress,
          ),
          const SizedBox(height: 30),
          CustomUploadCard(
            key: const ValueKey('add-pharmacy-certificate-upload'),
            onTap: _submitting
                ? () {}
                : () => _pick((file) => _certificate = file),
            title: 'Upload Certificate',
            uploaded: _certificate != null,
          ),
          const SizedBox(height: 16),
          CustomUploadCard(
            key: const ValueKey('add-pharmacy-license-upload'),
            onTap: _submitting ? () {} : () => _pick((file) => _license = file),
            title: 'Upload License',
            uploaded: _license != null,
          ),
          const SizedBox(height: 40),
          CustomButton(
            key: const ValueKey('add-pharmacy-submit-button'),
            text: 'Submit for Review',
            isLoading: _submitting,
            onPressed: _submitting ? null : _submit,
          ),
        ],
      ),
    );
  }

  Widget _successView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.hourglass_top,
              size: 64,
              color: AppColors.pendingOrange,
            ),
            const SizedBox(height: 20),
            const Text(
              'Pending admin approval',
              key: ValueKey('add-pharmacy-success-title'),
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            const Text(
              'Your pharmacy was submitted and is waiting for an administrator '
              'to review it. Your current pharmacy is unchanged and stays '
              'active. The new one appears in the pharmacy selector once it is '
              'approved.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 28),
            CustomButton(
              key: const ValueKey('add-pharmacy-done-button'),
              text: 'Done',
              onPressed: () => Navigator.pop(context, true),
            ),
          ],
        ),
      ),
    );
  }

  void _applyLocation(PharmacyLocationDraft picked) {
    setState(() {
      _latitude = picked.latitude;
      _longitude = picked.longitude;
      _suggestedAddress = picked.suggestedAddress;
    });
  }

  void _clearLocation() {
    setState(() {
      _latitude = null;
      _longitude = null;
      _suggestedAddress = null;
    });
  }

  void _useSuggestedAddress() {
    final suggestion = _suggestedAddress;
    if (suggestion == null) return;
    setState(() {
      _addressController.text = suggestion;
      _suggestedAddress = null;
    });
  }

  Future<void> _submit() async {
    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<AuthCubit>();

    final name = _nameController.text.trim();
    final address = _addressController.text.trim();

    if (name.isEmpty || address.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Enter the pharmacy name and address.')),
      );
      return;
    }
    if (_certificate == null || _license == null) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Upload both the certificate and the license.'),
        ),
      );
      return;
    }

    setState(() => _submitting = true);

    final latitude = _latitude;
    final longitude = _longitude;
    final data = FormData.fromMap({
      'pharmacy_name': name,
      'pharmacy_address': address,
      // Sent only as a pair; a lone coordinate is a 422 from the backend.
      if (latitude != null && longitude != null) ...{
        'latitude': latitude,
        'longitude': longitude,
      },
      'certificate': await MultipartFile.fromFile(_certificate!.path),
      'license': await MultipartFile.fromFile(_license!.path),
    });

    final error = await cubit.addPharmacy(data);
    if (!mounted) return;

    setState(() {
      _submitting = false;
      _submitted = error == null;
    });

    if (error != null) {
      messenger.showSnackBar(
        SnackBar(
          backgroundColor: AppColors.errorRed,
          content: Text(
            userFacingError(error, fallback: 'Unable to add the pharmacy.'),
          ),
        ),
      );
    }
  }
}
