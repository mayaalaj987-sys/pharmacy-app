import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/auth/presentation/pages/settings_edit_profile_page.dart';
import '../../theme/app_colors.dart';

class SettingsProfileCard extends StatelessWidget {
  const SettingsProfileCard({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<AuthCubit>().session;
    final actor = session?.actor;
    final image = actor?.profileImage;
    final status = session?.activePharmacy?.status ?? actor?.status ?? 'active';

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
          CircleAvatar(
            radius: 35,
            backgroundColor: AppColors.veryLightGreen,
            backgroundImage: image == null ? null : NetworkImage(image),
            child: image == null
                ? Text(
                    actor?.name.isNotEmpty == true ? actor!.name[0] : '?',
                    style: const TextStyle(
                      fontSize: 25,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  )
                : null,
          ),
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
                  status.toUpperCase(),
                  style: const TextStyle(
                    color: AppColors.successGreen,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const SettingsEditProfilePage(),
                ),
              );
            },
            icon: const Icon(Icons.edit),
            color: AppColors.tealGreen,
          ),
        ],
      ),
    );
  }
}
