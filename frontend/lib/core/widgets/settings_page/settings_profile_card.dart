import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/auth/data/models/auth_session_model.dart';
import '../../../features/auth/presentation/pages/settings_edit_profile_page.dart';
import '../../../features/account/presentation/widgets/profile_avatar.dart';
import '../../theme/app_colors.dart';

class SettingsProfileCard extends StatelessWidget {
  final AuthSession? sessionOverride;

  const SettingsProfileCard({super.key, this.sessionOverride});

  @override
  Widget build(BuildContext context) {
    final session = sessionOverride ?? context.watch<AuthCubit>().session;
    final actor = session?.actor;
    final image = actor?.profileImageUrl;
    final activePharmacy = session?.activePharmacy;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          ProfileAvatar(imageUrl: image, name: actor?.name ?? ''),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  actor?.name ?? 'Account',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  actor?.email ?? '',
                  style: const TextStyle(color: AppColors.secondaryText),
                ),
                const SizedBox(height: 5),
                Text(
                  actor?.phone?.trim().isNotEmpty == true
                      ? actor!.phone!
                      : 'No phone number',
                  style: const TextStyle(color: AppColors.secondaryText),
                ),
                const SizedBox(height: 5),
                Text(
                  activePharmacy?.name ?? 'No active pharmacy',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () async {
              final updated = await Navigator.push<bool>(
                context,
                MaterialPageRoute(
                  builder: (_) => const SettingsEditProfilePage(),
                ),
              );
              if (updated == true && context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Profile updated successfully.'),
                  ),
                );
              }
            },
            icon: const Icon(Icons.edit),
            color: AppColors.tealGreen,
          ),
        ],
      ),
    );
  }
}
