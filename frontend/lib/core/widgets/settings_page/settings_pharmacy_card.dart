import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/auth/presentation/pages/settings_edit_pharmacy_page.dart';
import '../../theme/app_colors.dart';

class SettingsPharmacyCard extends StatelessWidget {
  const SettingsPharmacyCard({super.key});

  @override
  Widget build(BuildContext context) {
    final pharmacy = context.watch<AuthCubit>().session?.activePharmacy;
    if (pharmacy == null) return const SizedBox.shrink();

    final location = pharmacy.latitude == null || pharmacy.longitude == null
        ? 'Location not selected'
        : '${pharmacy.latitude!.toStringAsFixed(5)}, ${pharmacy.longitude!.toStringAsFixed(5)}';

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              pharmacy.name,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            _line(Icons.location_on_outlined, pharmacy.address),
            _line(Icons.map_outlined, location),
            _line(
              Icons.verified_outlined,
              'Approval: ${pharmacy.status.toUpperCase()}',
            ),
            if (pharmacy.certificateOnFile || pharmacy.licenseOnFile)
              _line(
                Icons.description_outlined,
                [
                  if (pharmacy.certificateOnFile) 'Certificate on file',
                  if (pharmacy.licenseOnFile) 'License on file',
                ].join(' • '),
              ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: OutlinedButton.icon(
                onPressed: () async {
                  final updated = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const SettingsEditPharmacyPage(),
                    ),
                  );
                  if (updated == true && context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Pharmacy profile updated successfully.'),
                      ),
                    );
                  }
                },
                icon: const Icon(Icons.edit_location_alt_outlined),
                label: const Text('Edit Pharmacy'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _line(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 19, color: AppColors.tealGreen),
          const SizedBox(width: 8),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}
