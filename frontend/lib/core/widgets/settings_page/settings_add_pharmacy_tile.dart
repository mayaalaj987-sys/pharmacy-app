import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/auth/presentation/pages/add_pharmacy_page.dart';
import '../../network/user_facing_error.dart';
import '../../theme/app_colors.dart';

/// Registers an additional pharmacy, and picks up ones an admin has approved.
///
/// The refresh action exists because a pharmacy approved after sign-in only
/// reaches the client on the next `/me` call. It runs on demand rather than on
/// a timer — nothing here polls.
class SettingsAddPharmacyTile extends StatefulWidget {
  const SettingsAddPharmacyTile({super.key});

  @override
  State<SettingsAddPharmacyTile> createState() =>
      _SettingsAddPharmacyTileState();
}

class _SettingsAddPharmacyTileState extends State<SettingsAddPharmacyTile> {
  bool _refreshing = false;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<AuthCubit>().session;
    if (session == null || session.actor.type.name != 'pharmacist') {
      return const SizedBox.shrink();
    }

    final pending = session.availablePharmacies
        .where((pharmacy) => pharmacy.status == 'pending')
        .length;

    return Column(
      children: [
        Card(
          color: AppColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          child: ListTile(
            key: const ValueKey('settings-add-pharmacy-tile'),
            leading: const Icon(
              Icons.add_business_outlined,
              color: AppColors.tealGreen,
            ),
            title: const Text('Add Pharmacy'),
            subtitle: Text(
              pending == 0
                  ? 'Register another pharmacy under your account'
                  : '$pending awaiting admin approval',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () async {
              final added = await Navigator.push<bool>(
                context,
                MaterialPageRoute(builder: (_) => const AddPharmacyPage()),
              );
              if (added == true && context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text(
                      'Pharmacy submitted and waiting for admin approval.',
                    ),
                  ),
                );
              }
            },
          ),
        ),
        const SizedBox(height: 12),
        Card(
          color: AppColors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          child: ListTile(
            key: const ValueKey('settings-refresh-pharmacies-tile'),
            leading: const Icon(Icons.refresh, color: AppColors.tealGreen),
            title: const Text('Check for Approvals'),
            subtitle: const Text(
              'Reload your pharmacies after an admin review',
            ),
            trailing: _refreshing
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : null,
            onTap: _refreshing ? null : _refresh,
          ),
        ),
      ],
    );
  }

  Future<void> _refresh() async {
    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<AuthCubit>();
    final before = cubit.session?.approvedPharmacies.length ?? 0;

    setState(() => _refreshing = true);
    final error = await cubit.reloadSession();
    if (!mounted) return;
    setState(() => _refreshing = false);

    final after = cubit.session?.approvedPharmacies.length ?? 0;

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          error != null
              ? userFacingError(error, fallback: 'Unable to refresh.')
              : after > before
              ? 'A newly approved pharmacy is now available to select.'
              : 'Pharmacies are up to date.',
        ),
      ),
    );
  }
}
