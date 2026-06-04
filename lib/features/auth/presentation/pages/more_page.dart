import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';

class MorePage extends StatelessWidget {
  const MorePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "more options"),
      ),

      backgroundColor: AppColors.veryLightGreen,

      body: SafeArea(
       child:SingleChildScrollView(
         child : Column(
        // =========================
        // GRID
        // =========================
        children: [GridView.count(
          crossAxisCount: 3,

          mainAxisSpacing: 20,

          crossAxisSpacing: 20,

          childAspectRatio: 0.9,

          children: const [
            MenuCard(
              icon: Icons.inventory_2_rounded,
              title: 'Inventory',
              color: Colors.blue,
            ),

            MenuCard(
              icon: Icons.receipt_long_rounded,
              title: 'Sales',
              color: Colors.green,
            ),

            MenuCard(
              icon: Icons.local_shipping_rounded,
              title: 'Suppliers',
              color: Colors.orange,
            ),

            MenuCard(
              icon: Icons.shopping_bag_rounded,
              title: 'Purchases',
              color: Colors.purple,
            ),

            MenuCard(
              icon: Icons.settings_rounded,
              title: 'Settings',
              color: Colors.teal,
            ),

            MenuCard(
              icon: Icons.logout_rounded,
              title: 'Logout',
              color: Colors.red,
            ),
    ]
          ],

        ),
      ),
      )
      )
    );
  }
}

class MenuCard extends StatelessWidget {
  final IconData icon;

  final String title;

  final Color color;

  const MenuCard({
    super.key,

    required this.icon,

    required this.title,

    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        debugPrint(title);
      },

      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,

          borderRadius: BorderRadius.circular(24),

          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),

              blurRadius: 10,

              offset: const Offset(0, 5),
            ),
          ],
        ),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            Container(
              width: 65,
              height: 65,

              decoration: BoxDecoration(
                color: color.withOpacity(0.12),

                shape: BoxShape.circle,
              ),

              child: Icon(icon, size: 32, color: color),
            ),

            const SizedBox(height: 14),

            Text(
              title,

              style: const TextStyle(
                fontSize: 15,

                fontWeight: FontWeight.w600,

                color: AppColors.darkGreen,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
