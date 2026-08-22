import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../account/presentation/cubit/account_cubit.dart';
import '../../../account/presentation/cubit/account_state.dart';

class SettingsChangePasswordPage extends StatefulWidget {
  const SettingsChangePasswordPage({super.key});

  @override
  State<SettingsChangePasswordPage> createState() =>
      _SettingsChangePasswordPageState();
}

class _SettingsChangePasswordPageState
    extends State<SettingsChangePasswordPage> {
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmationController = TextEditingController();

  @override
  void initState() {
    super.initState();
    context.read<AccountCubit>().clearFeedback();
  }

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Change Password')),
      body: BlocBuilder<AccountCubit, AccountState>(
        builder: (context, state) {
          final loading =
              state.loading && state.operation == AccountOperation.password;
          final currentPasswordError = _error(state, 'current_password');
          final newPasswordError = _error(state, 'new_password');
          final generalError = state.operation == AccountOperation.password
              ? state.error?.message
              : null;
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              _passwordField(
                key: const ValueKey('current-password-field'),
                controller: _currentController,
                label: 'Current password',
                enabled: !loading,
                error: currentPasswordError,
              ),
              const SizedBox(height: 16),
              _passwordField(
                key: const ValueKey('new-password-field'),
                controller: _newController,
                label: 'New password',
                enabled: !loading,
                error: newPasswordError,
              ),
              const SizedBox(height: 16),
              _passwordField(
                key: const ValueKey('confirm-password-field'),
                controller: _confirmationController,
                label: 'Confirm new password',
                enabled: !loading,
              ),
              if (generalError != null &&
                  generalError != currentPasswordError &&
                  generalError != newPasswordError) ...[
                const SizedBox(height: 12),
                Text(
                  generalError,
                  key: const ValueKey('password-error'),
                  style: const TextStyle(color: AppColors.errorRed),
                ),
              ],
              const SizedBox(height: 28),
              CustomButton(
                text: 'Change Password',
                isLoading: loading,
                onPressed: loading ? null : _submit,
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _passwordField({
    required Key key,
    required TextEditingController controller,
    required String label,
    required bool enabled,
    String? error,
  }) {
    return TextField(
      key: key,
      controller: controller,
      enabled: enabled,
      obscureText: true,
      decoration: InputDecoration(
        labelText: label,
        errorText: error,
        prefixIcon: const Icon(Icons.lock_outline),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
      ),
    );
  }

  Future<void> _submit() async {
    if (_currentController.text.isEmpty || _newController.text.length < 8) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Enter your current password and a new password of at least 8 characters.',
          ),
        ),
      );
      return;
    }
    if (_newController.text != _confirmationController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('New password confirmation does not match.'),
        ),
      );
      return;
    }

    final success = await context.read<AccountCubit>().changePassword(
      currentPassword: _currentController.text,
      newPassword: _newController.text,
      confirmation: _confirmationController.text,
    );
    if (success && mounted) {
      Navigator.pop(context, true);
    }
  }

  String? _error(AccountState state, String field) {
    if (state.operation != AccountOperation.password) return null;
    final values = state.error?.fieldErrors[field];
    return values == null || values.isEmpty ? null : values.first;
  }
}
