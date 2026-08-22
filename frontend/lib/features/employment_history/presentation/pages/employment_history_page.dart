import 'package:flutter/material.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employment_history_api.dart';
import '../../domain/employment_record.dart';

/// Work history, and the verdict this side owes on each finished job.
///
/// One screen serves both parties. The rule is the same from either end — you
/// may rate a job you held, once it has ended, once — so writing it twice would
/// only let the two copies drift.
class EmploymentHistoryPage extends StatefulWidget {
  /// True when a pharmacist is looking at everyone who has worked for them,
  /// false when an employee is looking at their own past jobs.
  final bool asPharmacy;

  const EmploymentHistoryPage({super.key, required this.asPharmacy});

  @override
  State<EmploymentHistoryPage> createState() => _EmploymentHistoryPageState();
}

class _EmploymentHistoryPageState extends State<EmploymentHistoryPage> {
  final _api = EmploymentHistoryApi();

  List<EmploymentRecord>? _records;
  AuthApiException? _error;
  int? _savingId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final records = widget.asPharmacy
          ? await _api.pharmacyHistory()
          : await _api.myHistory();
      if (mounted) setState(() => _records = records);
    } on AuthApiException catch (error) {
      if (mounted) setState(() => _error = error);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = const AuthApiException(
            message: 'Unable to load the history.',
          );
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(60),
        child: CustomAppBar(
          title: widget.asPharmacy ? 'Who has worked here' : 'My work history',
          showNotificationBell: false,
        ),
      ),
      body: _body(),
    );
  }

  Widget _body() {
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            userFacingError(_error, fallback: 'Unable to load the history.'),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    final records = _records;
    if (records == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (records.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            widget.asPharmacy
                ? 'Nobody has worked here yet.'
                : 'You have not worked anywhere yet. Jobs you take will be '
                      'listed here.',
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: records.map(_card).toList(),
      ),
    );
  }

  Widget _card(EmploymentRecord record) {
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
                    record.counterpartName,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.lightGreen.withValues(alpha: .15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    record.shiftLabel,
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
              ],
            ),
            if (record.counterpartDetail != null) ...[
              const SizedBox(height: 4),
              Text(
                record.counterpartDetail!,
                style: const TextStyle(fontSize: 12, color: Colors.black54),
              ),
            ],
            const SizedBox(height: 6),
            Text(
              '${record.outcomeLabel} · ${record.days} day(s)'
              '${record.salary == null ? '' : ' · ${money(record.salary!)}'}',
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
            const SizedBox(height: 10),
            if (record.isRunning)
              const Text(
                'You can rate this once the job has ended.',
                style: TextStyle(fontSize: 11, color: Colors.black54),
              )
            else
              _stars(record),
          ],
        ),
      ),
    );
  }

  Widget _stars(EmploymentRecord record) {
    final busy = _savingId == record.id;

    return Row(
      children: [
        Text(
          record.myRating == null ? 'Rate:' : 'Your rating:',
          style: const TextStyle(fontSize: 12),
        ),
        const SizedBox(width: 6),
        for (var star = 1; star <= 5; star++)
          IconButton(
            key: ValueKey('rate-${record.id}-$star'),
            visualDensity: VisualDensity.compact,
            constraints: const BoxConstraints(minWidth: 34, minHeight: 34),
            padding: EdgeInsets.zero,
            onPressed: busy ? null : () => _rate(record, star),
            icon: Icon(
              (record.myRating ?? 0) >= star ? Icons.star : Icons.star_border,
              size: 20,
              color: AppColors.darkGreen,
            ),
          ),
        if (busy)
          const SizedBox.square(
            dimension: 14,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
      ],
    );
  }

  Future<void> _rate(EmploymentRecord record, int stars) async {
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _savingId = record.id);

    try {
      if (widget.asPharmacy) {
        await _api.rateEmployee(record.id, stars);
      } else {
        await _api.ratePharmacy(record.id, stars);
      }
      // Refetched rather than patched: rating again replaces the previous
      // verdict server-side, and the list is what proves which one stuck.
      await _load();

      if (mounted) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Thanks — your rating was recorded.')),
        );
      }
    } on AuthApiException catch (error) {
      if (mounted) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              userFacingError(error, fallback: 'Unable to save the rating.'),
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Unable to save the rating.')),
        );
      }
    } finally {
      if (mounted) setState(() => _savingId = null);
    }
  }
}
