import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/account/presentation/cubit/account_cubit.dart';
import '../../../features/account/presentation/cubit/account_state.dart';
import '../../../features/auth/data/models/auth_api_exception.dart';
import '../../theme/app_colors.dart';

class SettingsDeactivateAccountTile extends StatelessWidget {
  final Future<bool> Function(String password)? onDeactivate;

  const SettingsDeactivateAccountTile({super.key, this.onDeactivate});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Colors.red.shade50,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      child: ListTile(
        leading: const Icon(
          Icons.person_off_outlined,
          color: AppColors.errorRed,
          size: 28,
        ),
        title: const Text(
          'Deactivate Account',
          style: TextStyle(
            color: AppColors.errorRed,
            fontWeight: FontWeight.bold,
          ),
        ),
        subtitle: const Text('Preserves pharmacy records and history'),
        trailing: const Icon(Icons.chevron_right, color: AppColors.errorRed),
        onTap: () => showDialog<void>(
          context: context,
          barrierDismissible: false,
          builder: (_) => _DeactivateAccountDialog(onDeactivate: onDeactivate),
        ),
      ),
    );
  }
}

class _DeactivateAccountDialog extends StatefulWidget {
  final Future<bool> Function(String password)? onDeactivate;

  const _DeactivateAccountDialog({this.onDeactivate});

  @override
  State<_DeactivateAccountDialog> createState() =>
      _DeactivateAccountDialogState();
}

class _DeactivateAccountDialogState extends State<_DeactivateAccountDialog> {
  final _passwordController = TextEditingController();
  bool _submitted = false;
  bool _callbackLoading = false;

  @override
  void initState() {
    super.initState();
    if (widget.onDeactivate == null) {
      context.read<AccountCubit>().clearFeedback();
    }
  }

  @override
  void dispose() {
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.onDeactivate != null) {
      return _dialog(loading: _callbackLoading, error: null);
    }

    return BlocBuilder<AccountCubit, AccountState>(
      builder: (context, state) {
        final loading =
            state.loading && state.operation == AccountOperation.deactivation;
        final error = state.operation == AccountOperation.deactivation
            ? state.error
            : null;
        return _dialog(loading: loading, error: error);
      },
    );
  }

  Widget _dialog({required bool loading, required AuthApiException? error}) {
    return AlertDialog(
      title: const Text(
        'Deactivate Account',
        style: TextStyle(color: AppColors.errorRed),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'You will no longer be able to sign in. Pharmacy, stock, sales, and financial history will be preserved for audit integrity.',
            ),
            const SizedBox(height: 16),
            TextField(
              key: const ValueKey('deactivation-password-field'),
              controller: _passwordController,
              enabled: !loading,
              obscureText: true,
              decoration: InputDecoration(
                labelText: 'Current password',
                errorText: _firstError(error, 'current_password'),
                border: const OutlineInputBorder(),
              ),
            ),
            if (error != null) ...[
              const SizedBox(height: 10),
              Text(
                error.message,
                key: const ValueKey('deactivation-error'),
                style: const TextStyle(color: AppColors.errorRed),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: loading ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          key: const ValueKey('deactivate-confirm-button'),
          style: FilledButton.styleFrom(backgroundColor: AppColors.errorRed),
          onPressed: loading || _submitted ? null : _deactivate,
          child: loading
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text('Deactivate'),
        ),
      ],
    );
  }

  Future<void> _deactivate() async {
    if (_passwordController.text.isEmpty) {
      setState(() => _submitted = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Current password is required.')),
      );
      return;
    }
    setState(() => _submitted = true);
    if (widget.onDeactivate != null) {
      setState(() => _callbackLoading = true);
    }
    final success =
        await (widget.onDeactivate?.call(_passwordController.text) ??
            context.read<AccountCubit>().deactivate(_passwordController.text));
    if (mounted && !success) {
      setState(() {
        _submitted = false;
        _callbackLoading = false;
      });
    } else if (mounted && widget.onDeactivate != null) {
      setState(() => _callbackLoading = false);
    }
  }

  String? _firstError(AuthApiException? error, String field) {
    final values = error?.fieldErrors[field];
    return values == null || values.isEmpty ? null : values.first;
  }
}
