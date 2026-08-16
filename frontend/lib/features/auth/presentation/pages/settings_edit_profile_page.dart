import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../account/presentation/cubit/account_cubit.dart';
import '../../../account/presentation/cubit/account_state.dart';
import '../../../account/presentation/widgets/profile_avatar.dart';
import '../cubit/auth_cubit.dart';

class SettingsEditProfilePage extends StatefulWidget {
  const SettingsEditProfilePage({super.key});

  @override
  State<SettingsEditProfilePage> createState() =>
      _SettingsEditProfilePageState();
}

class _SettingsEditProfilePageState extends State<SettingsEditProfilePage> {
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;
  XFile? _selectedImage;

  @override
  void initState() {
    super.initState();
    final actor = context.read<AuthCubit>().session!.actor;
    _nameController = TextEditingController(text: actor.name);
    _emailController = TextEditingController(text: actor.email);
    _phoneController = TextEditingController(text: actor.phone ?? '');
    context.read<AccountCubit>().clearFeedback();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Edit Profile")),

      body: BlocBuilder<AccountCubit, AccountState>(
        builder: (context, state) {
          final actor = context.read<AuthCubit>().session!.actor;
          final isLoading =
              state.loading && state.operation == AccountOperation.profile;
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Center(
                child: ProfileAvatar(
                  imageUrl: actor.profileImageUrl,
                  localImagePath: _selectedImage?.path,
                  name: _nameController.text,
                  radius: 48,
                ),
              ),
              TextButton.icon(
                onPressed: isLoading ? null : _pickImage,
                icon: const Icon(Icons.photo_camera_outlined),
                label: const Text('Change Photo'),
              ),
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('profile-name-field'),
                controller: _nameController,
                enabled: !isLoading,
                decoration: _decoration(
                  'Full name',
                  Icons.person_outline,
                  error: _fieldError(state, 'name'),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('profile-email-field'),
                controller: _emailController,
                readOnly: true,
                decoration: _decoration(
                  'Email (read-only)',
                  Icons.email_outlined,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('profile-phone-field'),
                controller: _phoneController,
                enabled: !isLoading,
                keyboardType: TextInputType.phone,
                decoration: _decoration(
                  'Phone',
                  Icons.phone_outlined,
                  error: _fieldError(state, 'phone'),
                ),
              ),
              if (state.operation == AccountOperation.profile &&
                  state.error != null) ...[
                const SizedBox(height: 12),
                Text(
                  state.error!.message,
                  key: const ValueKey('profile-error'),
                  style: const TextStyle(color: AppColors.errorRed),
                ),
              ],
              const SizedBox(height: 28),
              CustomButton(
                text: 'Save Changes',
                isLoading: isLoading,
                onPressed: isLoading ? null : _save,
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _pickImage() async {
    final selected = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 90,
    );
    if (selected != null && mounted) {
      setState(() => _selectedImage = selected);
    }
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Name is required.')));
      return;
    }

    final success = await context.read<AccountCubit>().updateProfile(
      name: name,
      phone: _phoneController.text.trim().isEmpty
          ? null
          : _phoneController.text.trim(),
      profileImage: _selectedImage,
    );
    if (success && mounted) {
      Navigator.pop(context, true);
    }
  }

  InputDecoration _decoration(String label, IconData icon, {String? error}) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, color: AppColors.tealGreen),
      errorText: error,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
    );
  }

  String? _fieldError(AccountState state, String field) {
    if (state.operation != AccountOperation.profile) return null;
    final errors = state.error?.fieldErrors[field];
    return errors == null || errors.isEmpty ? null : errors.first;
  }
}
