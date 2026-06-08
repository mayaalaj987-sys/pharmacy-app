import 'package:flutter/material.dart';

import '../../../../core/data/medicine_data.dart';
import '../../../../core/data/sales_data.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/widgets/pos_page/pos_empty_cart.dart';
import '../../../../core/widgets/pos_page/pos_payment_methods.dart';
import '../../../../core/widgets/pos_page/pos_search_results.dart';
import '../../../../core/widgets/pos_page/pos_search_section.dart';
import '../../../../core/widgets/pos_page/pos_total_section.dart';

import '../../data/models/medicine_model.dart';
import '../../data/models/pos_cart_item_model.dart';
import '../../data/models/sale_item_model.dart';
import '../../data/models/sale_model.dart';

class PosPage extends StatefulWidget {
  const PosPage({super.key});

  @override
  State<PosPage> createState() => _PosPageState();
}

class _PosPageState extends State<PosPage> {
  final searchController = TextEditingController();

  final customerController = TextEditingController();

  String selectedPayment = "Cash";

  List<PosCartItem> cartItems = [];

  @override
  void initState() {
    super.initState();

    searchController.addListener(() {
      setState(() {});
    });
  }

  @override
  void dispose() {
    searchController.dispose();
    customerController.dispose();
    super.dispose();
  }

  List<MedicineModel> get filteredMedicines {
    if (searchController.text.isEmpty) {
      return [];
    }

    return medicines.where((medicine) {
      return medicine.name
          .toLowerCase()
          .contains(
        searchController.text.toLowerCase(),
      ) ||

          medicine.barcode
              .toLowerCase()
              .contains(
            searchController.text.toLowerCase(),
          );
    }).toList();
  }

  double get total {
    double sum = 0;

    for (var item in cartItems) {
      sum += item.total;
    }

    return sum;
  }

  void addToCart(MedicineModel medicine) {
    final stock = int.tryParse(medicine.quantity) ?? 0;

    final existingIndex = cartItems.indexWhere(
          (item) => item.medicine.name == medicine.name,
    );

    if (existingIndex != -1) {
      if (cartItems[existingIndex].quantity < stock) {
        cartItems[existingIndex].quantity++;
      }
    } else {
      if (stock > 0) {
        cartItems.add(
          PosCartItem(
            medicine: medicine,
            quantity: 1,
          ),
        );
      }
    }

    setState(() {});
  }

  void increaseQuantity(int index) {
    final stock =
        int.tryParse(cartItems[index].medicine.quantity) ?? 0;

    if (cartItems[index].quantity < stock) {
      setState(() {
        cartItems[index].quantity++;
      });
    }
  }

  void decreaseQuantity(int index) {
    setState(() {
      if (cartItems[index].quantity > 1) {
        cartItems[index].quantity--;
      } else {
        cartItems.removeAt(index);
      }
    });
  }

  void completeSale() {
    if (cartItems.isEmpty) return;

    final saleTotal = total;
    final paymentMethod = selectedPayment;

    for (var item in cartItems) {
      final medicineIndex = medicines.indexWhere(
            (m) => m.name == item.medicine.name,
      );

      if (medicineIndex != -1) {
        final oldMedicine = medicines[medicineIndex];

        medicines[medicineIndex] = MedicineModel(
          name: oldMedicine.name,
          category: oldMedicine.category,
          manufacturer: oldMedicine.manufacturer,
          sellingPrice: oldMedicine.sellingPrice,
          costPrice: oldMedicine.costPrice,
          quantity:
          (int.parse(oldMedicine.quantity) - item.quantity).toString(),
          reorderLevel: oldMedicine.reorderLevel,
          expiryDate: oldMedicine.expiryDate,
          barcode: oldMedicine.barcode,
          notes: oldMedicine.notes,
        );
      }
    }

    sales.add(
      SaleModel(
        invoiceNumber:
        DateTime.now().millisecondsSinceEpoch.toString(),
        customerName: customerController.text,
        totalAmount: saleTotal.toStringAsFixed(2),
        paymentMethod: paymentMethod,
        date: DateTime.now().toString(),
        items: cartItems.map((item) {
          return SaleItemModel(
            medicineName: item.medicine.name,
            quantity: item.quantity,
            price: double.parse(item.medicine.sellingPrice),
          );
        }).toList(),
      ),
    );

    showDialog(
      context: context,
      builder: (_) {
        return AlertDialog(
          title: const Text("Sale Completed"),

          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.check_circle,
                color: Colors.green,
                size: 70,
              ),

              const SizedBox(height: 16),

              Text(
                "Total: \$${saleTotal.toStringAsFixed(2)}",
              ),

              Text(
                "Payment: $paymentMethod",
              ),
            ],
          ),

          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);

                setState(() {
                  cartItems.clear();
                  customerController.clear();
                  searchController.clear();
                });
              },
              child: const Text("OK"),
            ),
          ],
        );
      },
    );

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text("Sale Completed Successfully"),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: "Point of Sale",
        ),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            children: [
              const Divider(height: 1),

              PosSearchSection(
                controller: searchController,
              ),

              PosSearchResults(
                medicines: filteredMedicines,
                onAdd: addToCart,
              ),

              cartItems.isEmpty
                  ? const PosEmptyCart()
                  : ListView.builder(
                shrinkWrap: true,
                physics:
                const NeverScrollableScrollPhysics(),
                itemCount: cartItems.length,
                itemBuilder: (context, index) {
                  final item = cartItems[index];

                  return Card(
                    margin: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 6,
                    ),
                    child: Padding(
                      padding:
                      const EdgeInsets.all(12),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  item.medicine.name,
                                  style:
                                  const TextStyle(
                                    fontWeight:
                                    FontWeight.bold,
                                  ),
                                ),
                              ),

                              IconButton(
                                onPressed: () {
                                  setState(() {
                                    cartItems.removeAt(
                                        index);
                                  });
                                },
                                icon: const Icon(
                                  Icons.close,
                                ),
                              ),
                            ],
                          ),

                          Row(
                            children: [
                              IconButton(
                                onPressed: () {
                                  decreaseQuantity(
                                      index);
                                },
                                icon: const Icon(
                                  Icons.remove,
                                ),
                              ),

                              Text(
                                item.quantity
                                    .toString(),
                              ),

                              IconButton(
                                onPressed: () {
                                  increaseQuantity(
                                      index);
                                },
                                icon: const Icon(
                                  Icons.add,
                                ),
                              ),

                              const Spacer(),

                              Text(
                                "\$${item.total.toStringAsFixed(2)}",
                                style:
                                const TextStyle(
                                  color:
                                  Colors.green,
                                  fontWeight:
                                  FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),

              Container(
                padding: const EdgeInsets.all(20),

                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius:
                  const BorderRadius.vertical(
                    top: Radius.circular(30),
                  ),
                ),

                child: Column(
                  children: [
                    CustomTextField(
                      controller: customerController,
                      hint: "Customer Name (Optional)",
                      prefixIcon: Icons.person,
                    ),

                    const SizedBox(height: 18),

                    PosPaymentMethods(
                      selectedPayment:
                      selectedPayment,
                      onChanged: (value) {
                        setState(() {
                          selectedPayment = value;
                        });
                      },
                    ),

                    const SizedBox(height: 22),

                    PosTotalSection(
                      total: total,
                      onCompleteSale:
                      completeSale,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}