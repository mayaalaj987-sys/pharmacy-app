import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../data/models/auth_session_model.dart';
import '../cubit/auth_cubit.dart';

class EmployeeSessionPage extends StatelessWidget {
  final AuthSession session;

  const EmployeeSessionPage({super.key, required this.session});

  @override
  Widget build(BuildContext context) {
    final actor = session.actor;
    final pharmacy = session.activePharmacy;
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
      appBar: AppBar(
        title: const Text('Employee Session'),
        actions: [
          IconButton(
            tooltip: 'Logout',
            onPressed: () => context.read<AuthCubit>().logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(actor.name, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                Text(actor.email),
                const SizedBox(height: 8),
                Text('Role: ${actor.role}'),
                Text('Status: ${actor.status ?? 'active'}'),
                if (pharmacy != null) ...[
                  const Divider(height: 32),
                  Text('Pharmacy: ${pharmacy.name}'),
                  Text(pharmacy.address),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
