class SaleModel {
  final String invoiceNumber;
  final String customerName;
  final String totalAmount;
  final String paymentMethod;
  final String date;

  SaleModel({
    required this.invoiceNumber,
    required this.customerName,
    required this.totalAmount,
    required this.paymentMethod,
    required this.date,
  });
}