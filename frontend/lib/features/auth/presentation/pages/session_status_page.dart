import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../data/models/auth_session_model.dart';
import '../cubit/auth_cubit.dart';

class SessionStatusPage extends StatelessWidget {
  final AuthSession session;

  const SessionStatusPage({super.key, required this.session});

  String get _title => switch (session.access.code) {
    'account_pending' => 'Account Under Review',
    'account_rejected' => 'Account Rejected',
    'no_pharmacy' => 'No Pharmacy Available',
    _ => 'Pharmacy Status',
  };

  String get _message => switch (session.access.code) {
    'account_pending' => 'Your account is waiting for pharmacist approval.',
    'account_rejected' => 'Your account application was rejected.',
    'no_pharmacy' => 'No pharmacy is currently assigned to this account.',
    'assigned_pharmacy_unavailable' =>
      'Your assigned pharmacy is not available for operations.',
    _ =>
      'No approved pharmacy is currently available. Review each pharmacy status below.',
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 48),
            const Icon(
              Icons.pending_actions,
              size: 80,
              color: AppColors.pendingOrange,
            ),
            const SizedBox(height: 24),
            Text(
              _title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 12),
            Text(_message, textAlign: TextAlign.center),
            const SizedBox(height: 24),
            for (final pharmacy in session.availablePharmacies)
              Card(
                child: ListTile(
                  leading: const Icon(Icons.local_pharmacy),
                  title: Text(pharmacy.name),
                  subtitle: Text(pharmacy.address),
                  trailing: Text(
                    pharmacy.status.toUpperCase(),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: () => context.read<AuthCubit>().refreshSession(),
              icon: const Icon(Icons.refresh),
              label: const Text('Refresh Status'),
            ),
            TextButton(
              onPressed: () => context.read<AuthCubit>().logout(),
              child: const Text('Logout'),
            ),
          ],
        ),
      ),
    );
  }
}
