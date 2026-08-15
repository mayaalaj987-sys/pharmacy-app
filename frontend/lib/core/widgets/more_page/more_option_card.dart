import 'package:flutter/material.dart';
import 'package:phamacy_managment/core/theme/app_colors.dart';

class MoreOptionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final VoidCallback onTap;

  const MoreOptionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),

      onTap: onTap,

      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
        ),

        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,

          children: [
            Icon(icon, size: 30,color: AppColors.tealGreen,),

            const SizedBox(height: 10),

            Text(title, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
