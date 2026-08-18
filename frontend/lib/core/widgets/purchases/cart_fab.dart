import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/purchase_cart/presentation/cubit/purchase_cart_cubit.dart';
import '../../../features/purchase_cart/presentation/cubit/purchase_cart_state.dart';
import '../../../features/purchase_cart/presentation/pages/purchase_cart_page.dart';
import '../../format/money.dart';
import '../../theme/app_colors.dart';

/// The way into the purchase cart, on every screen where buying happens.
///
/// Always shown, even empty. The app puts things in this cart by itself when
/// stock runs low, so a pharmacist who has never opened it still has to be able
/// to find it — a control that appears only once it is already needed is one
/// nobody learns.
class PurchaseCartFab extends StatelessWidget {
  const PurchaseCartFab({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<PurchaseCartCubit, PurchaseCartState>(
      builder: (context, state) {
        final cart = state.cart;

        return FloatingActionButton.extended(
          key: const ValueKey('open-cart-fab'),
          heroTag: 'purchase-cart-fab',
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const PurchaseCartPage()),
          ),
          backgroundColor: cart.suggestedCount > 0 ? AppColors.darkGreen : null,
          icon: Badge(
            isLabelVisible: cart.itemCount > 0,
            label: Text('${cart.itemCount}'),
            child: const Icon(Icons.shopping_cart_outlined),
          ),
          label: Text(cart.isEmpty ? 'Cart' : money(cart.total)),
        );
      },
    );
  }
}
