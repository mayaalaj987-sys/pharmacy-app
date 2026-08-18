import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../auth/data/models/auth_session_model.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../employee_offers/presentation/cubit/employee_offers_cubit.dart';
import '../../../employment_history/presentation/pages/employment_history_page.dart';
import '../../../employee_offers/presentation/cubit/employee_offers_state.dart';
import '../cubit/employee_workspace_cubit.dart';
import '../cubit/employee_workspace_state.dart';

/// Employee self-service account screen.
///
/// Only the fields the backend accepts are editable: display name, phone and
/// password. Email, role, salary, status and pharmacy are shown read-only
/// because the backend rejects them as prohibited attributes.
class EmployeeAccountPage extends StatefulWidget {
  final AuthActor actor;

  const EmployeeAccountPage({super.key, required this.actor});

  @override
  State<EmployeeAccountPage> createState() => _EmployeeAccountPageState();
}

class _EmployeeAccountPageState extends State<EmployeeAccountPage> {
  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _confirmPassword = TextEditingController();

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.actor.name);
    _phoneController = TextEditingController(text: widget.actor.phone ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _currentPassword.dispose();
    _newPassword.dispose();
    _confirmPassword.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "My Account", showNotificationBell: false),
      ),
      body: BlocBuilder<EmployeeWorkspaceCubit, EmployeeWorkspaceState>(
        builder: (context, state) {
          final busy = state.savingAccount;

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text(
                'Profile',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('employee-name-field'),
                controller: _nameController,
                enabled: !busy,
                decoration: const InputDecoration(
                  labelText: 'Full name',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('employee-phone-field'),
                controller: _phoneController,
                enabled: !busy,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: TextEditingController(text: widget.actor.email),
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Email (read-only)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: busy ? null : _saveProfile,
                child: busy
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Save profile'),
              ),

              const Divider(height: 40),

              const Text(
                'Change password',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              _passwordField(
                const ValueKey('employee-current-password'),
                _currentPassword,
                'Current password',
                busy,
              ),
              const SizedBox(height: 12),
              _passwordField(
                const ValueKey('employee-new-password'),
                _newPassword,
                'New password (min 8 characters)',
                busy,
              ),
              const SizedBox(height: 12),
              _passwordField(
                const ValueKey('employee-confirm-password'),
                _confirmPassword,
                'Confirm new password',
                busy,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: busy ? null : _changePassword,
                child: const Text('Change password'),
              ),

              const Divider(height: 40),
              const Text(
                'Work history',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              const Text(
                'Every job you have held, and the pharmacies you can rate.',
                style: TextStyle(fontSize: 12, color: Colors.black54),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('employee-history-button'),
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        const EmploymentHistoryPage(asPharmacy: false),
                  ),
                ),
                icon: const Icon(Icons.history, size: 18),
                label: const Text('My work history'),
              ),

              // Only shown while there is a job to leave. Reads from the offers
              // inbox rather than the session, because that is the screen the
              // person lands back on and it already knows.
              BlocBuilder<EmployeeOffersCubit, EmployeeOffersState>(
                builder: (context, offers) {
                  if (!offers.isEmployed) return const SizedBox.shrink();

                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Divider(height: 40),
                      const Text(
                        'Your job',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'You cover the ${offers.employment!.shift ?? ''} shift at '
                        '${offers.employment!.pharmacyName}.',
                        style: const TextStyle(fontSize: 13),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        key: const ValueKey('employee-resign-button'),
                        onPressed: busy ? null : _confirmResign,
                        icon: const Icon(Icons.logout, size: 18),
                        label: const Text('Leave this job'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.errorRed,
                        ),
                      ),
                    ],
                  );
                },
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _passwordField(
    Key key,
    TextEditingController controller,
    String label,
    bool busy,
  ) {
    return TextField(
      key: key,
      controller: controller,
      enabled: !busy,
      obscureText: true,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
    );
  }

  Future<void> _saveProfile() async {
    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<EmployeeWorkspaceCubit>();
    final name = _nameController.text.trim();

    if (name.length < 2) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Enter your full name.')),
      );
      return;
    }

    // An empty field means "not edited", never "erase my number". The backend
    // turns '' into null and writes it, so sending a blank would silently
    // delete the phone of anyone who opens this page and presses save.
    final phone = _phoneController.text.trim();
    final ok = await cubit.updateProfile(
      name: name,
      phone: phone.isEmpty ? null : phone,
    );

    if (ok && mounted) {
      // The shell reads the name from the session, not from this page.
      await context.read<AuthCubit>().reloadSession();
    }

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Profile updated.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to update profile.',
                ),
        ),
      ),
    );
  }

  Future<void> _changePassword() async {
    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<EmployeeWorkspaceCubit>();

    if (_currentPassword.text.isEmpty || _newPassword.text.length < 8) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Enter your current password and a new password of at least 8 characters.',
          ),
        ),
      );
      return;
    }
    if (_newPassword.text != _confirmPassword.text) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('New password confirmation does not match.'),
        ),
      );
      return;
    }

    final ok = await cubit.changePassword(
      currentPassword: _currentPassword.text,
      newPassword: _newPassword.text,
      confirmation: _confirmPassword.text,
    );

    if (ok) {
      _currentPassword.clear();
      _newPassword.clear();
      _confirmPassword.clear();
    }

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Password changed.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to change password.',
                ),
        ),
      ),
    );
  }

  Future<void> _confirmResign() async {
    final cubit = context.read<EmployeeOffersCubit>();
    final messenger = ScaffoldMessenger.of(context);
    final employment = cubit.state.employment;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Leave this job?'),
        content: Text(
          'You will stop working at ${employment?.pharmacyName ?? 'this pharmacy'} '
          'and your shift opens up for someone else. Any offers you were sent '
          'become available again.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Stay'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: TextButton.styleFrom(foregroundColor: AppColors.errorRed),
            child: const Text('Leave'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    final ok = await cubit.resign();
    if (!mounted) return;

    // On success the reloaded session sends AuthGate back to the unattached
    // shell, so this page goes with it; nothing to navigate here.
    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'You have left the job.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to leave this job.',
                ),
        ),
      ),
    );
  }
}
