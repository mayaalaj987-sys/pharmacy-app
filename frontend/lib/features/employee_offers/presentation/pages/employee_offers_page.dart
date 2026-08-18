import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../domain/job_offer.dart';
import '../cubit/employee_offers_cubit.dart';
import '../cubit/employee_offers_state.dart';

/// Every pharmacy that has asked for this person.
///
/// Offers are a record, not a queue: one that cannot be accepted stays listed
/// and greyed with the reason, because the day a job ends the old offers are
/// what this screen is for.
class EmployeeOffersPage extends StatefulWidget {
  const EmployeeOffersPage({super.key});

  @override
  State<EmployeeOffersPage> createState() => _EmployeeOffersPageState();
}

class _EmployeeOffersPageState extends State<EmployeeOffersPage> {
  @override
  void initState() {
    super.initState();
    context.read<EmployeeOffersCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<EmployeeOffersCubit, EmployeeOffersState>(
      builder: (context, state) {
        if (state.status == EmployeeOffersStatus.loading && state.offers.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (state.status == EmployeeOffersStatus.failure && state.offers.isEmpty) {
          return _centred(
            context,
            Icons.error_outline,
            userFacingError(state.error, fallback: 'Unable to load your offers.'),
            onRetry: () => context.read<EmployeeOffersCubit>().load(),
          );
        }

        if (state.offers.isEmpty) {
          return _centred(
            context,
            Icons.work_outline,
            'No offers yet. Your application is live, and pharmacies can see '
                'your name and read your CV.',
            onRetry: () => context.read<EmployeeOffersCubit>().load(),
          );
        }

        return RefreshIndicator(
          onRefresh: () => context.read<EmployeeOffersCubit>().load(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (state.isEmployed) _employedBanner(state.employment!),
              ...state.offers.map(_offerCard),
            ],
          ),
        );
      },
    );
  }

  Widget _employedBanner(OfferEmployment employment) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.veryLightGreen,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        'You work at ${employment.pharmacyName}'
        '${employment.shift == null ? '' : ' on the ${employment.shift} shift'}. '
        'Other offers stay here in case you need them later.',
        style: const TextStyle(fontSize: 12, color: AppColors.darkGreen),
      ),
    );
  }

  Widget _offerCard(JobOffer offer) {
    final pharmacy = offer.pharmacy;
    final owner = offer.owner;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    pharmacy?.name ?? 'Pharmacy',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                ),
                _chip(offer.shiftLabel),
              ],
            ),
            const SizedBox(height: 8),
            if (pharmacy != null && pharmacy.address.isNotEmpty)
              _row(Icons.place_outlined, pharmacy.address),
            if (pharmacy != null && pharmacy.hasLocation)
              _row(
                Icons.map_outlined,
                '${pharmacy.latitude!.toStringAsFixed(5)}, '
                    '${pharmacy.longitude!.toStringAsFixed(5)}',
              ),
            _row(
              Icons.payments_outlined,
              offer.salary == null
                  ? 'No salary specified'
                  : money(offer.salary!),
            ),
            if (owner != null)
              _row(
                Icons.person_outline,
                owner.contact == null
                    ? owner.name
                    : '${owner.name} — ${owner.contact}',
              ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              // Accepting arrives with the cutover. Until then the button shows
              // what will be possible and why it is not yet.
              child: AnimatedOpacity(
                duration: const Duration(milliseconds: 200),
                opacity: offer.acceptable ? 1.0 : 0.4,
                child: FilledButton.icon(
                  key: ValueKey('accept-offer-${offer.id}'),
                  onPressed: null,
                  icon: Icon(
                    offer.acceptable ? Icons.check : Icons.lock_outline,
                    size: 18,
                  ),
                  label: const Text('Accept offer'),
                ),
              ),
            ),
            if (!offer.acceptable && offer.unavailableExplanation != null) ...[
              const SizedBox(height: 6),
              Text(
                offer.unavailableExplanation!,
                key: ValueKey('offer-unavailable-${offer.id}'),
                style: const TextStyle(fontSize: 11, color: Colors.black54),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _row(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 15, color: Colors.black54),
          const SizedBox(width: 6),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 13))),
        ],
      ),
    );
  }

  Widget _chip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.lightGreen.withValues(alpha: .15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: const TextStyle(fontSize: 11)),
    );
  }

  Widget _centred(
    BuildContext context,
    IconData icon,
    String message, {
    required VoidCallback onRetry,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: Colors.black26),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Refresh'),
            ),
          ],
        ),
      ),
    );
  }
}
