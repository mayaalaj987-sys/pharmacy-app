import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/layout/responsive_layout.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../support/data/support_api.dart';
import '../../../support/data/support_repository.dart';
import '../../../support/presentation/cubit/support_cubit.dart';
import '../../../support/presentation/pages/support_page.dart';
import '../../data/models/auth_session_model.dart';
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
        final registration = state is PharmacistRegistrationStatus
            ? state.registration
            : null;
        final approved = registration?.isApproved ?? false;
        final refreshing = state is PharmacistRegistrationStatus
            ? state.refreshing
            : false;
        final hasRejectedPharmacy =
            registration?.pharmacies.any((item) => item.status == 'rejected') ??
            false;
        final theme = Theme.of(context);
        final scheme = theme.colorScheme;

        return Scaffold(
          appBar: AppBar(title: const Text('Registration status')),
          body: RefreshIndicator(
            onRefresh: () =>
                context.read<AuthCubit>().refreshRegistrationStatus(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                ResponsiveContent(
                  safeArea: false,
                  maxWidth: 720,
                  child: Column(
                    children: [
                      const SizedBox(height: 12),
                      Container(
                        width: 116,
                        height: 116,
                        decoration: BoxDecoration(
                          color: _statusColor(
                            registration?.status,
                            scheme,
                          ).withValues(alpha: .14),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          _statusIcon(registration?.status),
                          size: 62,
                          color: _statusColor(registration?.status, scheme),
                        ),
                      ),
                      const SizedBox(height: 26),
                      Text(
                        _title(registration?.status),
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineMedium,
                      ),
                      const SizedBox(height: 10),
                      Text(
                        registration?.message ??
                            'Your pharmacy registration is awaiting approval.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 28),
                      for (final pharmacy
                          in registration?.pharmacies ??
                              const <SessionPharmacy>[]) ...[
                        _PharmacyDecisionCard(pharmacy: pharmacy),
                        const SizedBox(height: 12),
                      ],
                      const SizedBox(height: 12),
                      if (!approved)
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: refreshing
                                ? null
                                : () => context
                                      .read<AuthCubit>()
                                      .refreshRegistrationStatus(),
                            icon: refreshing
                                ? SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: scheme.onPrimary,
                                    ),
                                  )
                                : const Icon(Icons.refresh_rounded),
                            label: Text(
                              refreshing ? 'Refreshing...' : 'Refresh status',
                            ),
                          ),
                        ),
                      if (hasRejectedPharmacy) ...[
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            key: const ValueKey('registration-contact-support'),
                            onPressed: () => _openSupport(context),
                            icon: const Icon(Icons.support_agent_rounded),
                            label: const Text('Contact support'),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'You can send a question and read the admin reply here.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                      if (approved)
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: () async {
                              await context
                                  .read<AuthCubit>()
                                  .goToLoginFromRegistration();
                              if (!context.mounted) return;
                              Navigator.pushAndRemoveUntil(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const LoginPage(),
                                ),
                                (route) => false,
                              );
                            },
                            icon: const Icon(Icons.login_rounded),
                            label: const Text('Go to login'),
                          ),
                        ),
                      const SizedBox(height: 28),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  static Future<void> _openSupport(BuildContext context) {
    return Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BlocProvider(
          create: (_) =>
              SupportCubit(SupportRepository(SupportApi.registration())),
          child: const SupportPage(),
        ),
      ),
    );
  }

  static String _title(String? status) => switch (status) {
    'approved' => 'Pharmacy approved',
    'rejected' => 'A decision needs your attention',
    'no_pharmacy' => 'No pharmacy found',
    _ => 'Review in progress',
  };

  static IconData _statusIcon(String? status) => switch (status) {
    'approved' => Icons.check_circle_rounded,
    'rejected' => Icons.cancel_rounded,
    'no_pharmacy' => Icons.error_outline_rounded,
    _ => Icons.hourglass_top_rounded,
  };

  static Color _statusColor(String? status, ColorScheme scheme) =>
      switch (status) {
        'approved' => AppColors.successGreen,
        'rejected' || 'no_pharmacy' => scheme.error,
        _ => AppColors.pendingOrange,
      };
}

class _PharmacyDecisionCard extends StatelessWidget {
  const _PharmacyDecisionCard({required this.pharmacy});

  final SessionPharmacy pharmacy;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final rejected = pharmacy.status == 'rejected';
    final approved = pharmacy.status == 'approved';
    final statusColor = approved
        ? AppColors.successGreen
        : rejected
        ? scheme.error
        : AppColors.pendingOrange;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Icon(
                      Icons.local_pharmacy_rounded,
                      color: statusColor,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(pharmacy.name, style: theme.textTheme.titleMedium),
                      const SizedBox(height: 3),
                      Text(
                        pharmacy.address,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    pharmacy.status.toUpperCase(),
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: statusColor,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            if (rejected) ...[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: scheme.errorContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          Icons.info_outline_rounded,
                          size: 18,
                          color: scheme.onErrorContainer,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          'Reason from the administrator',
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: scheme.onErrorContainer,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      pharmacy.rejectionReason ??
                          'No detailed reason was provided. Contact support for clarification.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.onErrorContainer,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
