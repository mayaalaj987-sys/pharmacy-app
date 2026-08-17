import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_text_field.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../../core/widgets/pos_page/pos_empty_cart.dart';
import '../../../../core/widgets/pos_page/pos_payment_methods.dart';
import '../../../../core/widgets/pos_page/pos_search_results.dart';
import '../../../../core/widgets/pos_page/pos_search_section.dart';
import '../../../../core/widgets/pos_page/pos_total_section.dart';

import '../../../inventory/domain/medicine.dart';
import '../../../inventory/presentation/cubit/inventory_cubit.dart';
import '../../../inventory/presentation/cubit/inventory_state.dart';
import '../../../sales/domain/pos_cart_item.dart';
import '../../../sales/presentation/cubit/sales_cubit.dart';
import '../../../../core/format/money.dart';

class PosPage extends StatefulWidget {
  const PosPage({super.key});

  @override
  State<PosPage> createState() => _PosPageState();
}

class _PosPageState extends State<PosPage> {
  final searchController = TextEditingController();

  final customerController = TextEditingController();

  String selectedPayment = "Cash";

  final cardNumberController = TextEditingController();

  List<PosCartItem> cartItems = [];

  @override
  void initState() {
    super.initState();

    context.read<InventoryCubit>().load();

    searchController.addListener(() {
      setState(() {});
    });
  }

  @override
  void dispose() {
    searchController.dispose();
    customerController.dispose();
    cardNumberController.dispose();
    super.dispose();
  }

  /// Search over authoritative server stock, never a local list.
  List<Medicine> filteredMedicines(List<Medicine> stock) {
    final query = searchController.text.trim().toLowerCase();
    if (query.isEmpty) return const <Medicine>[];

    return stock.where((medicine) {
      return medicine.name.toLowerCase().contains(query) ||
          (medicine.qrCode ?? '').toLowerCase().contains(query);
    }).toList();
  }

  double get total {
    double sum = 0;

    for (var item in cartItems) {
      sum += item.total;
    }

    return sum;
  }

  void addToCart(Medicine medicine) {
    final stock = medicine.quantity;

    final existingIndex = cartItems.indexWhere(
      (item) => item.medicine.id == medicine.id,
    );

    if (existingIndex != -1) {
      if (cartItems[existingIndex].quantity < stock) {
        cartItems[existingIndex].quantity++;
      }
    } else {
      if (stock > 0) {
        cartItems.add(PosCartItem(medicine: medicine, quantity: 1));
      }
    }

    setState(() {});
  }

  void increaseQuantity(int index) {
    final stock = cartItems[index].medicine.quantity;

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

  /// Backend payment values are lowercase; card requires a 10-digit number.
  String get _paymentValue => selectedPayment.toLowerCase();

  Future<void> completeSale() async {
    if (cartItems.isEmpty) return;

    final cardNumber = cardNumberController.text.trim();
    if (_paymentValue == 'card' &&
        (cardNumber.length != 10 || int.tryParse(cardNumber) == null)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Card payments require a 10-digit card number.'),
        ),
      );
      return;
    }

    final messenger = ScaffoldMessenger.of(context);
    final salesCubit = context.read<SalesCubit>();
    final inventoryCubit = context.read<InventoryCubit>();

    final success = await salesCubit.createSale(
      items: cartItems
          .map(
            (item) => <String, dynamic>{
              'medicine_id': item.medicine.id,
              'quantity': item.quantity,
            },
          )
          .toList(),
      paymentMethod: _paymentValue,
      customerName: customerController.text,
      cardNumber: _paymentValue == 'card' ? cardNumber : null,
    );

    if (!mounted) return;

    if (!success) {
      // Surfaces backend rules such as insufficient stock verbatim.
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            userFacingError(
              salesCubit.state.error,
              context: ErrorContext.sale,
              fallback: 'The sale could not be completed.',
            ),
          ),
        ),
      );
      return;
    }

    // Stock was decremented inside the backend transaction; refresh from server.
    await inventoryCubit.load();

    if (!mounted) return;

    final completedTotal = salesCubit.state.sales.isNotEmpty
        ? salesCubit.state.sales.first.totalPrice
        : total;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text("Sale Completed"),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.check_circle, color: Colors.green, size: 70),
              const SizedBox(height: 16),
              Text("Total: ${money(completedTotal)}"),
              Text("Payment: $selectedPayment"),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text("OK"),
            ),
          ],
        );
      },
    );

    if (!mounted) return;
    setState(() {
      cartItems.clear();
      customerController.clear();
      searchController.clear();
      cardNumberController.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Point of Sale"),
      ),

      body: SafeArea(
        child: BlocBuilder<InventoryCubit, InventoryState>(
          builder: (context, inventoryState) {
            return SingleChildScrollView(
              child: Column(
                children: [
                  const Divider(height: 1),

                  PosSearchSection(controller: searchController),

                  if (inventoryState.status == InventoryStatus.loading)
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: LinearProgressIndicator(),
                    ),

                  if (inventoryState.status == InventoryStatus.failure)
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              userFacingError(
                                inventoryState.error,
                                fallback: 'Unable to load stock.',
                              ),
                              style: const TextStyle(color: AppColors.errorRed),
                            ),
                          ),
                          TextButton(
                            onPressed: () =>
                                context.read<InventoryCubit>().load(),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),

                  PosSearchResults(
                    medicines: filteredMedicines(inventoryState.medicines),
                    onAdd: addToCart,
                  ),

                  cartItems.isEmpty
                      ? const PosEmptyCart()
                      : ListView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: cartItems.length,
                          itemBuilder: (context, index) {
                            final item = cartItems[index];

                            return Card(
                              margin: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 6,
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(12),
                                child: Column(
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            item.medicine.name,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                        ),

                                        IconButton(
                                          onPressed: () {
                                            setState(() {
                                              cartItems.removeAt(index);
                                            });
                                          },
                                          icon: const Icon(Icons.close),
                                        ),
                                      ],
                                    ),

                                    Row(
                                      children: [
                                        IconButton(
                                          onPressed: () {
                                            decreaseQuantity(index);
                                          },
                                          icon: const Icon(Icons.remove),
                                        ),

                                        Text(item.quantity.toString()),

                                        IconButton(
                                          onPressed: () {
                                            increaseQuantity(index);
                                          },
                                          icon: const Icon(Icons.add),
                                        ),

                                        const Spacer(),

                                        Text(
                                          money(item.total),
                                          style: const TextStyle(
                                            color: Colors.green,
                                            fontWeight: FontWeight.bold,
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
                      borderRadius: const BorderRadius.vertical(
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
                          selectedPayment: selectedPayment,
                          onChanged: (value) {
                            setState(() {
                              selectedPayment = value;
                            });
                          },
                        ),

                        if (selectedPayment == "Card") ...[
                          const SizedBox(height: 16),
                          CustomTextField(
                            controller: cardNumberController,
                            hint: "Card Number (10 digits)",
                            prefixIcon: Icons.credit_card,
                            keyboardType: TextInputType.number,
                          ),
                        ],

                        const SizedBox(height: 22),

                        PosTotalSection(
                          total: total,
                          onCompleteSale: completeSale,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
