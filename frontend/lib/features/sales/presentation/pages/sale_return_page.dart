import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/layout/responsive_layout.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../auth/data/models/auth_api_exception.dart';
import '../../../inventory/presentation/cubit/inventory_cubit.dart';
import '../../domain/sale.dart';
import '../cubit/sales_cubit.dart';

class SaleReturnPage extends StatefulWidget {
  const SaleReturnPage({super.key, required this.saleId});

  final int saleId;

  @override
  State<SaleReturnPage> createState() => _SaleReturnPageState();
}

class _SaleReturnPageState extends State<SaleReturnPage> {
  final _note = TextEditingController();
  late Future<SaleReturnable> _future;
  int? _selectedLineId;
  int _quantity = 1;
  String _reason = 'unwanted';
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _future = context.read<SalesCubit>().repository.fetchReturnable(
      widget.saleId,
    );
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Return from invoice #${widget.saleId}')),
      body: FutureBuilder<SaleReturnable>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(
              message: snapshot.error is AuthApiException
                  ? userFacingError(
                      snapshot.error as AuthApiException,
                      fallback: 'Unable to load return details.',
                    )
                  : 'Unable to load return details.',
              onRetry: () => setState(_reload),
            );
          }

          final details = snapshot.data!;
          final available = details.items
              .where((item) => item.returnable > 0)
              .toList(growable: false);
          if (!details.isOpen) {
            return const _ClosedState(
              icon: Icons.timer_off_outlined,
              title: 'Return window closed',
              message: 'This invoice is outside the 48-hour return window.',
            );
          }
          if (available.isEmpty) {
            return const _ClosedState(
              icon: Icons.assignment_turned_in_outlined,
              title: 'Nothing left to return',
              message: 'All items on this invoice were already returned.',
            );
          }

          final selected = available.firstWhere(
            (item) => item.saleItemId == _selectedLineId,
            orElse: () => available.first,
          );
          final safeQuantity = _quantity.clamp(1, selected.returnable);
          final theme = Theme.of(context);

          return SingleChildScrollView(
            child: ResponsiveContent(
              maxWidth: 680,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.schedule_rounded,
                          color: theme.colorScheme.onPrimaryContainer,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            '${details.hoursLeft} hours remain to process this return.',
                            style: TextStyle(
                              color: theme.colorScheme.onPrimaryContainer,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text('Medicine', style: theme.textTheme.titleMedium),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    key: ValueKey('return-line-${selected.saleItemId}'),
                    initialValue: selected.saleItemId,
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.medication_outlined),
                    ),
                    items: available
                        .map(
                          (item) => DropdownMenuItem(
                            value: item.saleItemId,
                            child: Text(
                              '${item.name} · ${item.returnable} returnable',
                            ),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() {
                            _selectedLineId = value;
                            _quantity = 1;
                          }),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    key: ValueKey(
                      'return-quantity-${selected.saleItemId}-$safeQuantity',
                    ),
                    initialValue: safeQuantity,
                    decoration: const InputDecoration(
                      labelText: 'Quantity',
                      prefixIcon: Icon(Icons.numbers_rounded),
                    ),
                    items: List.generate(
                      selected.returnable,
                      (index) => DropdownMenuItem(
                        value: index + 1,
                        child: Text('${index + 1}'),
                      ),
                    ),
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() => _quantity = value ?? 1),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    key: ValueKey('return-reason-$_reason'),
                    initialValue: _reason,
                    decoration: const InputDecoration(
                      labelText: 'Reason',
                      prefixIcon: Icon(Icons.help_outline_rounded),
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'unwanted',
                        child: Text('Customer no longer wants it'),
                      ),
                      DropdownMenuItem(
                        value: 'wrong_item',
                        child: Text('Wrong item supplied'),
                      ),
                      DropdownMenuItem(
                        value: 'damaged',
                        child: Text('Returned damaged — do not restock'),
                      ),
                    ],
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() => _reason = value!),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _note,
                    enabled: !_submitting,
                    maxLength: 255,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Note (optional)',
                      alignLabelWithHint: true,
                    ),
                  ),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.surfaceContainerHighest,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Row(
                      children: [
                        const Text('Refund'),
                        const Spacer(),
                        Text(
                          money(selected.unitPrice * safeQuantity),
                          style: theme.textTheme.titleMedium,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      key: const ValueKey('confirm-sale-return'),
                      onPressed: _submitting
                          ? null
                          : () => _submit(selected, safeQuantity),
                      icon: _submitting
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.keyboard_return_rounded),
                      label: const Text('Confirm refund'),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _submit(ReturnableSaleItem item, int quantity) async {
    setState(() => _submitting = true);
    try {
      final result = await context.read<SalesCubit>().repository.returnItem(
        saleId: widget.saleId,
        saleItemId: item.saleItemId,
        quantity: quantity,
        reason: _reason,
        note: _note.text,
      );
      if (!mounted) return;
      await context.read<InventoryCubit>().load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${money(result.refundAmount)} refunded. '
            '${result.restocked ? 'Stock was restored.' : 'Damaged stock was written off.'}',
          ),
        ),
      );
      _note.clear();
      _selectedLineId = null;
      _quantity = 1;
      setState(() {
        _submitting = false;
        _reload();
      });
    } on AuthApiException catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            userFacingError(error, fallback: 'Unable to process this return.'),
          ),
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to process this return.')),
      );
    }
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return _ClosedState(
      icon: Icons.error_outline_rounded,
      title: 'Could not load return',
      message: message,
      action: OutlinedButton.icon(
        onPressed: onRetry,
        icon: const Icon(Icons.refresh_rounded),
        label: const Text('Retry'),
      ),
    );
  }
}

class _ClosedState extends StatelessWidget {
  const _ClosedState({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 64, color: Theme.of(context).colorScheme.outline),
            const SizedBox(height: 16),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: 14), action!],
          ],
        ),
      ),
    );
  }
}
