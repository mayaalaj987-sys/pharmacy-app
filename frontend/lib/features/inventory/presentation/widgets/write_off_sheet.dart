import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/format/money.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../domain/medicine.dart';
import '../cubit/inventory_cubit.dart';

/// Books stock off the shelf when it was not sold.
///
/// The alternative, and what pharmacists did before this existed, was to edit
/// the quantity down. That records neither what happened nor what it cost, and
/// the money leaves the books without appearing in a single report: stock
/// never sold never enters cost of goods, so its cost reduced profit at no
/// point in its life while still counting as an asset.
Future<void> showWriteOffSheet(BuildContext context, Medicine batch) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (sheetContext) => BlocProvider.value(
      value: context.read<InventoryCubit>(),
      child: _WriteOffSheet(batch: batch),
    ),
  );
}

/// The four things that actually happen to stock, and how each reads.
const _reasons = <String, String>{
  'expired': 'Expired',
  'damaged': 'Damaged or spoiled',
  'lost': 'Missing at stock count',
  'returned_to_supplier': 'Returned to the supplier',
};

class _WriteOffSheet extends StatefulWidget {
  final Medicine batch;

  const _WriteOffSheet({required this.batch});

  @override
  State<_WriteOffSheet> createState() => _WriteOffSheetState();
}

class _WriteOffSheetState extends State<_WriteOffSheet> {
  late final TextEditingController _quantity;
  final _note = TextEditingController();
  late String _reason;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    // Expired stock is written off whole; anything else is usually a few boxes.
    _reason = widget.batch.isExpired ? 'expired' : 'damaged';
    _quantity = TextEditingController(
      text: widget.batch.isExpired ? '${widget.batch.quantity}' : '1',
    );
  }

  @override
  void dispose() {
    _quantity.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final batch = widget.batch;
    final entered = int.tryParse(_quantity.text.trim()) ?? 0;
    final loss = batch.costPrice * entered;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Write off stock',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            '${batch.name} · ${batch.quantity} on the shelf · '
            '${money(batch.costPrice)} each',
            style: const TextStyle(fontSize: 12, color: Colors.black54),
          ),
          const SizedBox(height: 16),
          TextField(
            key: const ValueKey('write-off-quantity'),
            controller: _quantity,
            enabled: !_busy,
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              labelText: 'How many',
              errorText: _error,
              isDense: true,
              border: const OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: const ValueKey('write-off-reason'),
            initialValue: _reason,
            decoration: const InputDecoration(
              labelText: 'Why',
              isDense: true,
              border: OutlineInputBorder(),
            ),
            items: _reasons.entries
                .map(
                  (entry) => DropdownMenuItem(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (value) => setState(() => _reason = value ?? _reason),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey('write-off-note'),
            controller: _note,
            enabled: !_busy,
            decoration: const InputDecoration(
              labelText: 'Note (optional)',
              isDense: true,
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color:
                  (_reason == 'returned_to_supplier'
                          ? AppColors.darkGreen
                          : AppColors.errorRed)
                      .withValues(alpha: .08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              _reason == 'returned_to_supplier'
                  // Replaced or refunded, so charging the pharmacy for it would
                  // be inventing a loss.
                  ? 'Going back to the supplier, so this is not counted as a '
                        'loss — only the stock comes off the shelf.'
                  : '${money(loss)} will be recorded as a loss and subtracted '
                        'from this period\'s profit.',
              style: const TextStyle(fontSize: 12),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _busy ? null : () => Navigator.pop(context),
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  key: const ValueKey('write-off-confirm'),
                  onPressed: _busy ? null : _submit,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.errorRed,
                  ),
                  child: _busy
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Write off'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    final quantity = int.tryParse(_quantity.text.trim());

    if (quantity == null || quantity < 1) {
      setState(() => _error = 'Enter how many boxes.');

      return;
    }
    if (quantity > widget.batch.quantity) {
      setState(() => _error = 'Only ${widget.batch.quantity} on the shelf.');

      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<InventoryCubit>();

    final ok = await cubit.writeOff(
      widget.batch.id,
      quantity: quantity,
      reason: _reason,
      note: _note.text.trim(),
    );

    if (!mounted) return;
    Navigator.pop(context);

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? null : AppColors.errorRed,
        content: Text(
          ok
              ? 'Written off. The loss is recorded against this period.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Could not write that off.',
                ),
        ),
      ),
    );
  }
}
