import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/layout/responsive_layout.dart';
import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/inventory_repository.dart';
import '../cubit/inventory_cubit.dart';

class WriteOffHistoryPage extends StatefulWidget {
  const WriteOffHistoryPage({super.key});

  @override
  State<WriteOffHistoryPage> createState() => _WriteOffHistoryPageState();
}

class _WriteOffHistoryPageState extends State<WriteOffHistoryPage> {
  late Future<StockWriteOffHistory> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = context.read<InventoryCubit>().repository.fetchWriteOffs();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Stock write-off history')),
      body: FutureBuilder<StockWriteOffHistory>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      error is AuthApiException
                          ? error.message
                          : 'Unable to load write-off history.',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => setState(_load),
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final history = snapshot.data!;
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
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.errorContainer,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Recorded stock loss'),
                            const SizedBox(height: 5),
                            Text(
                              money(history.totalValue),
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),
                      if (history.records.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 80),
                          child: Center(
                            child: Text('No stock write-offs recorded.'),
                          ),
                        )
                      else
                        for (final record in history.records) ...[
                          _WriteOffCard(record: record),
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
}

class _WriteOffCard extends StatelessWidget {
  const _WriteOffCard({required this.record});

  final StockWriteOffRecord record;

  @override
  Widget build(BuildContext context) {
    final reason = record.reason.replaceAll('_', ' ');
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: CircleAvatar(
          child: Icon(
            record.reason == 'expired'
                ? Icons.event_busy_outlined
                : Icons.remove_shopping_cart_outlined,
          ),
        ),
        title: Text(record.medicineName),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text('${record.quantity} units · $reason'),
            if (record.note != null) Text(record.note!),
            if (record.recordedAt != null) Text(_date(record.recordedAt!)),
          ],
        ),
        trailing: Text(
          money(record.value),
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
    );
  }

  static String _date(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
