import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import 'login_page.dart';

class PendingPage extends StatelessWidget {
  const PendingPage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is PharmacistRegistrationStatus &&
            state.errorMessage != null) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(state.errorMessage!)));
        }
      },
      builder: (context, state) {
        final status = state is PharmacistRegistrationStatus
            ? state.registration
            : null;
        final approved = status?.isApproved ?? false;
        final refreshing = state is PharmacistRegistrationStatus
            ? state.refreshing
            : false;

        return Scaffold(
          backgroundColor: AppColors.veryLightGreen,
          body: SafeArea(
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                const SizedBox(height: 56),
                Container(
                  width: 140,
                  height: 140,
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.05),
                        blurRadius: 20,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Icon(
                    approved
                        ? Icons.check_circle_rounded
                        : Icons.access_time_rounded,
                    size: 80,
                    color: approved
                        ? AppColors.darkGreen
                        : AppColors.pendingOrange,
                  ),
                ),
                const SizedBox(height: 40),
                Text(
                  approved ? 'Pharmacy Approved' : 'Pharmacy Status',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                    color: AppColors.darkGreen,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  status?.message ??
                      'Your pharmacy registration is awaiting approval.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 16,
                    height: 1.6,
                    color: AppColors.secondaryText,
                  ),
                ),
                const SizedBox(height: 20),
                for (final pharmacy in status?.pharmacies ?? const [])
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
                if (!approved)
                  FilledButton.icon(
                    onPressed: refreshing
                        ? null
                        : () => context
                              .read<AuthCubit>()
                              .refreshRegistrationStatus(),
                    icon: refreshing
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.refresh),
                    label: Text(
                      refreshing ? 'Refreshing...' : 'Refresh Status',
                    ),
                  ),
                if (approved)
                  FilledButton(
                    onPressed: () async {
                      await context
                          .read<AuthCubit>()
                          .goToLoginFromRegistration();
                      if (!context.mounted) return;
                      Navigator.pushAndRemoveUntil(
                        context,
                        MaterialPageRoute(builder: (_) => const LoginPage()),
                        (route) => false,
                      );
                    },
                    child: const Text('Go to Login'),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}
