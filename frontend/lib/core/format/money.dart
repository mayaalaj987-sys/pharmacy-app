/// Currency formatting for the Syrian Pound (SYP).
///
/// Every monetary value in the app is displayed through this helper so the
/// currency is defined in exactly one place. Amounts are shown with thousands
/// separators because Syrian Pound values are large.
library;

const String kCurrencyCode = 'SYP';

/// Formats [amount] as `12,500 SYP`.
///
/// Fractional pounds are not used in practice, so the default drops the
/// decimals. Pass [decimals] when an exact figure matters (e.g. an invoice
/// total that came back from the server with cents).
String money(num? amount, {int decimals = 0}) {
  final value = (amount ?? 0).toDouble();
  final negative = value < 0;
  final fixed = value.abs().toStringAsFixed(decimals);

  final parts = fixed.split('.');
  final grouped = _group(parts.first);
  final body = parts.length > 1 ? '$grouped.${parts[1]}' : grouped;

  return '${negative ? '-' : ''}$body $kCurrencyCode';
}

String _group(String digits) {
  final buffer = StringBuffer();

  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(',');
    buffer.write(digits[i]);
  }

  return buffer.toString();
}
