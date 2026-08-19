import 'package:flutter_test/flutter_test.dart';
import 'package:phamacy_managment/features/inventory/domain/medicine.dart';

/// Collapsing a shelf of batches into what the till should show.
///
/// This has to match the server's allocation exactly. If they disagree the
/// counter quotes a price the sale does not charge, which is the worst kind of
/// bug to find out about from a customer.
void main() {
  Medicine batch({
    required int id,
    String name = 'Amoxicillin 500mg',
    required int quantity,
    int? expiresInDays,
    double sellingPrice = 12500,
  }) {
    return Medicine(
      id: id,
      name: name,
      category: 'Antibiotics',
      manufacturer: 'Qasioun Labs',
      sellingPrice: sellingPrice,
      costPrice: 8000,
      quantity: quantity,
      reorderLevel: 10,
      expireDate: expiresInDays == null
          ? null
          : DateTime.now().add(Duration(days: expiresInDays)),
    );
  }

  test('two batches of one drug become one line', () {
    final shelf = Medicine.collapseBatches([
      batch(id: 1, quantity: 10, expiresInDays: 20),
      batch(id: 2, quantity: 100, expiresInDays: 900),
    ]);

    expect(shelf, hasLength(1));
    // The whole shelf, because a sale spills into the next batch.
    expect(shelf.single.quantity, 110);
  });

  test('the line stands for the batch that will be sold first', () {
    // Its price is the price charged and its date is the one worth warning
    // about — both taken from the earliest batch still in date.
    final shelf = Medicine.collapseBatches([
      batch(id: 2, quantity: 100, expiresInDays: 900, sellingPrice: 14000),
      batch(id: 1, quantity: 10, expiresInDays: 20, sellingPrice: 9000),
    ]);

    expect(shelf.single.id, 1);
    expect(shelf.single.sellingPrice, 9000);
    expect(shelf.single.daysUntilExpiry, 20);
  });

  test('expired batches are left out of the count entirely', () {
    // They cannot be sold, so counting them would promise stock that the till
    // will refuse.
    final shelf = Medicine.collapseBatches([
      batch(id: 1, quantity: 40, expiresInDays: -10),
      batch(id: 2, quantity: 60, expiresInDays: 400),
    ]);

    expect(shelf.single.id, 2);
    expect(shelf.single.quantity, 60);
  });

  test('a drug whose every batch expired disappears from the till', () {
    final shelf = Medicine.collapseBatches([
      batch(id: 1, quantity: 40, expiresInDays: -10),
      batch(id: 2, quantity: 5, expiresInDays: -100),
    ]);

    expect(shelf, isEmpty);
  });

  test('undated stock sorts last', () {
    // It has no claim to being urgent, and putting it first is how a dated
    // batch ends up thrown away.
    final shelf = Medicine.collapseBatches([
      batch(id: 1, quantity: 50, expiresInDays: null, sellingPrice: 20000),
      batch(id: 2, quantity: 50, expiresInDays: 400, sellingPrice: 12500),
    ]);

    expect(shelf.single.id, 2);
    expect(shelf.single.sellingPrice, 12500);
    expect(shelf.single.quantity, 100);
  });

  test('different drugs stay separate', () {
    final shelf = Medicine.collapseBatches([
      batch(id: 1, name: 'Amoxicillin 500mg', quantity: 10, expiresInDays: 100),
      batch(id: 2, name: 'Aspirin 100mg', quantity: 20, expiresInDays: 100),
      batch(id: 3, name: 'Amoxicillin 500mg', quantity: 30, expiresInDays: 500),
    ]);

    expect(shelf, hasLength(2));
    expect(shelf.firstWhere((m) => m.name == 'Amoxicillin 500mg').quantity, 40);
  });

  test('an empty shelf collapses to nothing', () {
    expect(Medicine.collapseBatches(const <Medicine>[]), isEmpty);
  });
}
