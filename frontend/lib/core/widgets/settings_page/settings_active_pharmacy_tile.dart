import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../theme/app_colors.dart';

class SettingsActivePharmacyTile extends StatelessWidget {
  const SettingsActivePharmacyTile({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<AuthCubit>().session;
    final approved = session?.approvedPharmacies ?? [];
    final active = session?.activePharmacy;

    if (session == null || approved.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: const Icon(Icons.local_pharmacy, color: AppColors.tealGreen),
        title: const Text(
          'Active Pharmacy',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Text(active?.name ?? 'Select a pharmacy'),
        trailing: approved.length > 1 ? const Icon(Icons.swap_horiz) : null,
        onTap: approved.length < 2
            ? null
            : () => showDialog<void>(
                context: context,
                builder: (dialogContext) => SimpleDialog(
                  title: const Text('Select Pharmacy'),
                  children: [
                    for (final pharmacy in approved)
                      SimpleDialogOption(
                        onPressed: () {
                          Navigator.pop(dialogContext);
                          context.read<AuthCubit>().selectActivePharmacy(
                            pharmacy.id,
                          );
                        },
                        child: Text(pharmacy.name),
                      ),
                  ],
                ),
              ),
      ),
    );
  }
}
