import 'package:flutter/material.dart';

/// Asks for a number of boxes.
///
/// A widget rather than a controller built inside a method, because awaiting
/// `showDialog` returns the moment the button is pressed — not when the dialog
/// has finished animating away. Disposing the controller on the line after the
/// await therefore destroys it while the dialog is still being built for its
/// exit, and the text field then throws "a TextEditingController was used after
/// being disposed". Owning it in a State hands the timing to the framework,
/// which tears the route down before it disposes anything inside it.
Future<int?> askQuantity(
  BuildContext context, {
  required String title,
  required String subtitle,
  required int initial,
  String? footnote,
  int? max,
  String confirmLabel = 'Confirm',
  Key? fieldKey,
  Key? confirmKey,
}) {
  return showDialog<int>(
    context: context,
    builder: (_) => _QuantityDialog(
      title: title,
      subtitle: subtitle,
      initial: initial,
      footnote: footnote,
      max: max,
      confirmLabel: confirmLabel,
      fieldKey: fieldKey,
      confirmKey: confirmKey,
    ),
  );
}

class _QuantityDialog extends StatefulWidget {
  final String title;
  final String subtitle;
  final int initial;
  final String? footnote;
  final int? max;
  final String confirmLabel;
  final Key? fieldKey;
  final Key? confirmKey;

  const _QuantityDialog({
    required this.title,
    required this.subtitle,
    required this.initial,
    required this.confirmLabel,
    this.footnote,
    this.max,
    this.fieldKey,
    this.confirmKey,
  });

  @override
  State<_QuantityDialog> createState() => _QuantityDialogState();
}

class _QuantityDialogState extends State<_QuantityDialog> {
  late final TextEditingController _quantity;
  String? _error;

  @override
  void initState() {
    super.initState();
    _quantity = TextEditingController(text: '${widget.initial}');
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.subtitle),
          const SizedBox(height: 12),
          TextField(
            key: widget.fieldKey,
            controller: _quantity,
            autofocus: true,
            keyboardType: TextInputType.number,
            onSubmitted: (_) => _confirm(),
            decoration: InputDecoration(
              labelText: 'Quantity',
              errorText: _error,
              border: const OutlineInputBorder(),
            ),
          ),
          if (widget.footnote != null) ...[
            const SizedBox(height: 8),
            Text(widget.footnote!, style: const TextStyle(color: Colors.grey)),
          ],
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          key: widget.confirmKey,
          onPressed: _confirm,
          child: Text(widget.confirmLabel),
        ),
      ],
    );
  }

  void _confirm() {
    final value = int.tryParse(_quantity.text.trim());

    if (value == null || value < 1) {
      setState(() => _error = 'Enter a quantity of 1 or more.');

      return;
    }

    // Only enforced where the caller says there is a ceiling. The supplier
    // catalogue is shared, so this figure can be stale by the time the request
    // lands — the server re-checks it under a lock and has the last word.
    final max = widget.max;
    if (max != null && value > max) {
      setState(() => _error = 'Only $max available.');

      return;
    }

    Navigator.pop(context, value);
  }
}
