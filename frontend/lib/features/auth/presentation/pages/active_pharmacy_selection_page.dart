import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../data/models/auth_session_model.dart';
import '../cubit/auth_cubit.dart';

class ActivePharmacySelectionPage extends StatelessWidget {
  final AuthSession session;
  final String? errorMessage;

  const ActivePharmacySelectionPage({
    super.key,
    required this.session,
    this.errorMessage,
  });

  @override
  Widget build(BuildContext context) {
    final pharmacies = session.approvedPharmacies;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Select Pharmacy'),
        actions: [
          IconButton(
            tooltip: 'Logout',
            onPressed: () => context.read<AuthCubit>().logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Choose the pharmacy you want to manage.',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (errorMessage != null) ...[
            const SizedBox(height: 12),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
          ],
          const SizedBox(height: 20),
          for (final pharmacy in pharmacies)
            Card(
              child: ListTile(
                leading: const Icon(Icons.local_pharmacy),
                title: Text(pharmacy.name),
                subtitle: Text(pharmacy.address),
                trailing: const Icon(Icons.chevron_right),
                onTap: () =>
                    context.read<AuthCubit>().selectActivePharmacy(pharmacy.id),
              ),
            ),
        ],
      ),
    );
  }
}
