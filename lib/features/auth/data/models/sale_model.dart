import 'sale_item_model.dart';

class SaleModel {
  final String invoiceNumber;
  final String customerName;
  final String totalAmount;
  final String paymentMethod;
  final String date;

  final List<SaleItemModel> items;

  SaleModel({
    required this.invoiceNumber,
    required this.customerName,
    required this.totalAmount,
    required this.paymentMethod,
    required this.date,
    required this.items,
  });
}