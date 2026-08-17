import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/inventory/domain/medicine.dart';

/// Shelf-status rules. These drive both the Inventory screen's grouping and
/// the point-of-sale guard, so they are pinned precisely — especially the
/// expiry cut-off, which must agree with the server's.
void main() {
  Medicine build({
    int quantity = 50,
    int? reorderLevel = 10,
    int? expiresInDays,
  }) {
    final now = DateTime.now();

    return Medicine(
      id: 1,
      name: 'Amoxicillin 500mg',
      category: 'Antibiotics',
      manufacturer: 'Qasioun Labs',
      sellingPrice: 12500,
      costPrice: 8000,
      quantity: quantity,
      reorderLevel: reorderLevel,
      expireDate: expiresInDays == null
          ? null
          : DateTime(now.year, now.month, now.day + expiresInDays),
    );
  }

  group('expiry', () {
    test('a medicine expiring today is not expired yet', () {
      final medicine = build(expiresInDays: 0);

      expect(medicine.isExpired, isFalse);
      expect(medicine.daysUntilExpiry, 0);
      // Today still counts as valid, matching the backend cut-off.
      expect(medicine.status, MedicineStatus.expiringSoon);
    });

    test('yesterday is expired', () {
      final medicine = build(expiresInDays: -1);

      expect(medicine.isExpired, isTrue);
      expect(medicine.daysUntilExpiry, -1);
      expect(medicine.status, MedicineStatus.expired);
    });

    test('inside the 90-day window counts as expiring soon', () {
      expect(build(expiresInDays: 1).isExpiringSoon, isTrue);
      expect(build(expiresInDays: 89).isExpiringSoon, isTrue);
      expect(build(expiresInDays: 90).isExpiringSoon, isTrue);
    });

    test('past the window it is simply valid', () {
      final medicine = build(expiresInDays: 91);

      expect(medicine.isExpiringSoon, isFalse);
      expect(medicine.status, MedicineStatus.healthy);
    });

    test('an expired medicine is never also flagged as expiring soon', () {
      final medicine = build(expiresInDays: -5);

      expect(medicine.isExpired, isTrue);
      expect(medicine.isExpiringSoon, isFalse);
    });

    test('no expiry date means no expiry claims', () {
      final medicine = build();

      expect(medicine.isExpired, isFalse);
      expect(medicine.isExpiringSoon, isFalse);
      expect(medicine.daysUntilExpiry, isNull);
    });
  });

  group('stock', () {
    test('at or below the reorder level needs reordering', () {
      expect(build(quantity: 10, expiresInDays: 400).isLowStock, isTrue);
      expect(build(quantity: 9, expiresInDays: 400).isLowStock, isTrue);
      expect(build(quantity: 11, expiresInDays: 400).isLowStock, isFalse);
    });

    test('a zero reorder level disables the rule', () {
      expect(build(quantity: 0, reorderLevel: 0).isLowStock, isFalse);
    });

    test('zero quantity is out of stock', () {
      expect(build(quantity: 0, expiresInDays: 400).isOutOfStock, isTrue);
      expect(build(quantity: 1, expiresInDays: 400).isOutOfStock, isFalse);
    });
  });

  group('status precedence', () {
    test('expired outranks every other condition', () {
      // Expired, out of stock and below reorder level all at once.
      final medicine = build(quantity: 0, reorderLevel: 10, expiresInDays: -3);

      expect(medicine.status, MedicineStatus.expired);
    });

    test('out of stock outranks the softer warnings', () {
      final medicine = build(quantity: 0, reorderLevel: 10, expiresInDays: 10);

      expect(medicine.status, MedicineStatus.outOfStock);
    });

    test('expiring soon outranks reorder', () {
      final medicine = build(quantity: 5, reorderLevel: 10, expiresInDays: 10);

      expect(medicine.status, MedicineStatus.expiringSoon);
    });

    test('healthy stock reports as valid', () {
      expect(
        build(quantity: 80, reorderLevel: 10, expiresInDays: 400).status,
        MedicineStatus.healthy,
      );
    });
  });

  test('every status has a display label', () {
    for (final status in MedicineStatus.values) {
      expect(status.label, isNotEmpty);
    }
    expect(MedicineStatus.expired.label, 'Expired');
    expect(MedicineStatus.reorder.label, 'Reorder');
  });

  test('parsing ignores the retired barcode field', () {
    final medicine = Medicine.fromJson({
      'id': 7,
      'name': 'Paracetamol 500mg',
      'category_medicine': 'Painkillers',
      'manufacturer': 'Orontes Labs',
      'selling_price': '6000.00',
      'cost_price': '3000.00',
      'quantity': 40,
      'reorder_level': 5,
      'expire_date': '2029-02-17',
      'qr_code': '1116',
    });

    expect(medicine.id, 7);
    expect(medicine.sellingPrice, 6000.0);
    expect(medicine.expireDate, DateTime(2029, 2, 17));
    expect(medicine.status, MedicineStatus.healthy);
  });
}
