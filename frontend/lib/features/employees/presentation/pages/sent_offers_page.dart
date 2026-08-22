import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/layout/responsive_layout.dart';
import '../../../auth/data/models/auth_api_exception.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../data/employees_repository.dart';
import '../../domain/sent_job_offer.dart';
import '../cubit/employees_cubit.dart';

class SentOffersPage extends StatefulWidget {
  const SentOffersPage({super.key});

  @override
  State<SentOffersPage> createState() => _SentOffersPageState();
}

class _SentOffersPageState extends State<SentOffersPage> {
  late Future<List<SentJobOffer>> _future;
  int? _withdrawingId;

  EmployeesRepository get _repository =>
      context.read<EmployeesCubit>().repository;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() => _future = _repository.fetchOffers();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sent job offers')),
      body: FutureBuilder<List<SentJobOffer>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    error is AuthApiException
                        ? error.message
                        : 'Unable to load sent offers.',
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () => setState(_load),
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Retry'),
                  ),
                ],
              ),
            );
          }

          final offers = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async {
              setState(_load);
              await _future;
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                ResponsiveContent(
                  safeArea: false,
                  maxWidth: 760,
                  child: offers.isEmpty
                      ? const Padding(
                          padding: EdgeInsets.symmetric(vertical: 100),
                          child: Center(child: Text('No offers sent yet.')),
                        )
                      : Column(
                          children: [
                            for (final offer in offers) ...[
                              _OfferCard(
                                offer: offer,
                                withdrawing: _withdrawingId == offer.id,
                                onWithdraw: offer.canWithdraw
                                    ? () => _confirmWithdraw(offer)
                                    : null,
                              ),
                              const SizedBox(height: 10),
                            ],
                          ],
                        ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _confirmWithdraw(SentJobOffer offer) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Withdraw this offer?'),
        content: Text(
          '${offer.applicantName} will no longer be able to accept the ${offer.shift} shift.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep offer'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Withdraw'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _withdrawingId = offer.id);
    try {
      await _repository.withdrawOffer(offer.id);
      if (!mounted) return;
      final pharmacyId = context.read<AuthCubit>().session?.activePharmacy?.id;
      if (pharmacyId != null) {
        await context.read<EmployeesCubit>().load(pharmacyId);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Offer withdrawn.')));
      setState(() {
        _withdrawingId = null;
        _load();
      });
    } on AuthApiException catch (error) {
      if (!mounted) return;
      setState(() => _withdrawingId = null);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({
    required this.offer,
    required this.withdrawing,
    required this.onWithdraw,
  });

  final SentJobOffer offer;
  final bool withdrawing;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final statusColor = switch (offer.status) {
      'accepted' => Colors.green,
      'withdrawn' => scheme.outline,
      _ => Colors.orange,
    };
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    offer.applicantName,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                Chip(
                  label: Text(offer.status.toUpperCase()),
                  labelStyle: TextStyle(color: statusColor),
                ),
              ],
            ),
            Text('${offer.applicantRole} · ${offer.shift} shift'),
            if (offer.salary != null) Text('Salary: ${money(offer.salary!)}'),
            if (offer.offeredAt != null)
              Text('Sent: ${_date(offer.offeredAt!)}'),
            if (onWithdraw != null) ...[
              const SizedBox(height: 10),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton.icon(
                  onPressed: withdrawing ? null : onWithdraw,
                  icon: withdrawing
                      ? const SizedBox.square(
                          dimension: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.undo_rounded),
                  label: const Text('Withdraw offer'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static String _date(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
