import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/core/format/money.dart';

void main() {
  test('formats Syrian Pound amounts with thousands separators', () {
    expect(money(12500), '12,500 SYP');
    expect(money(1000000), '1,000,000 SYP');
    expect(money(999), '999 SYP');
    expect(money(0), '0 SYP');
  });

  test('rounds to whole pounds by default', () {
    expect(money(12500.49), '12,500 SYP');
    expect(money(12500.5), '12,501 SYP');
  });

  test('keeps decimals when the caller asks for them', () {
    expect(money(12500.25, decimals: 2), '12,500.25 SYP');
    expect(money(1234567.5, decimals: 2), '1,234,567.50 SYP');
  });

  test('treats a null amount as zero', () {
    expect(money(null), '0 SYP');
  });

  test('keeps the sign in front of a negative amount', () {
    expect(money(-4500), '-4,500 SYP');
  });
}
