import 'package:flutter/material.dart';
import '../../../features/auth/data/models/pos_cart_item_model.dart';

class PosCartList extends StatelessWidget {
  final List<PosCartItem> items;

  final Function(int) onIncrease;
  final Function(int) onDecrease;
  final Function(int) onDelete;

  const PosCartList({
    super.key,
    required this.items,
    required this.onIncrease,
    required this.onDecrease,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),

      itemCount: items.length,

      itemBuilder: (context, index) {
        final item = items[index];

        return Card(
          margin: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 8,
          ),

          child: Padding(
            padding: const EdgeInsets.all(16),

            child: Column(
              children: [

                Row(
                  children: [

                    Expanded(
                      child: Text(
                        item.medicine.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),

                    IconButton(
                      onPressed: () {
                        onDelete(index);
                      },
                      icon: const Icon(
                        Icons.close,
                        color: Colors.red,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                Row(
                  children: [

                    IconButton(
                      onPressed: () {
                        onDecrease(index);
                      },
                      icon: const Icon(Icons.remove_circle),
                    ),

                    Text(
                      item.quantity.toString(),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    IconButton(
                      onPressed: () {
                        onIncrease(index);
                      },
                      icon: const Icon(
                        Icons.add_circle,
                        color: Colors.green,
                      ),
                    ),

                    const Spacer(),

                    Text(
                      "\$${item.total.toStringAsFixed(2)}",
                      style: const TextStyle(
                        fontSize: 18,
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
    );
  }
}