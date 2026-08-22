import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../theme/app_colors.dart';

class SettingsLogoutTile extends StatelessWidget {
  final Future<void> Function()? onLogout;

  const SettingsLogoutTile({super.key, this.onLogout});

  @override
  Widget build(BuildContext context) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: const Icon(Icons.logout, color: AppColors.pendingOrange),
        title: const Text(
          "Logout",
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        trailing: const Icon(
          Icons.arrow_forward_ios,
          size: 18,
          color: AppColors.tealGreen,
        ),
        onTap: () => showDialog<void>(
          context: context,
          barrierDismissible: false,
          builder: (_) => _LogoutDialog(
            onLogout: onLogout ?? context.read<AuthCubit>().logout,
          ),
        ),
      ),
    );
  }
}

class _LogoutDialog extends StatefulWidget {
  final Future<void> Function() onLogout;

  const _LogoutDialog({required this.onLogout});

  @override
  State<_LogoutDialog> createState() => _LogoutDialogState();
}

class _LogoutDialogState extends State<_LogoutDialog> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Text(
        'Logout',
        style: TextStyle(fontWeight: FontWeight.bold),
      ),
      content: const Text('Are you sure you want to logout?'),
      actions: [
        TextButton(
          onPressed: _loading ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          key: const ValueKey('logout-confirm-button'),
          onPressed: _loading ? null : _logout,
          child: _loading
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text('Logout'),
        ),
      ],
    );
  }

  Future<void> _logout() async {
    if (_loading) return;
    setState(() => _loading = true);
    await widget.onLogout();
    if (mounted) {
      setState(() => _loading = false);
    }
  }
}
