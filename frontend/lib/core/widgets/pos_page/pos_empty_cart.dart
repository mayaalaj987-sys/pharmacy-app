import 'package:flutter/material.dart';

class PosEmptyCart extends StatelessWidget {
  const PosEmptyCart({super.key});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 180,

      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,

          children: [
            Icon(
              Icons.shopping_cart_outlined,
              size: 80,
              color: Colors.grey.shade300,
            ),

            const SizedBox(height: 12),

            Text(
              'Search for a medicine to add',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 16,
                color: Colors.grey.shade500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}