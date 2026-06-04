import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

import '../../../../core/widgets/custom_button.dart';

import 'login_page.dart';

class PendingPage extends StatelessWidget {
  const PendingPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,

      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),

          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,

            children: [
              // =========================
              // ICON
              // =========================
              Container(
                width: 140,
                height: 140,

                decoration: BoxDecoration(
                  color: AppColors.white,

                  shape: BoxShape.circle,

                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),

                      blurRadius: 20,

                      offset: const Offset(0, 8),
                    ),
                  ],
                ),

                child: const Icon(
                  Icons.access_time_rounded,

                  size: 80,

                  color: AppColors.pendingOrange,
                ),
              ),

              const SizedBox(height: 40),

              // =========================
              // TITLE
              // =========================
              const Text(
                'Account Under Review',

                textAlign: TextAlign.center,

                style: TextStyle(
                  fontSize: 30,

                  fontWeight: FontWeight.bold,

                  color: AppColors.darkGreen,
                ),
              ),

              const SizedBox(height: 18),

              // =========================
              // DESCRIPTION
              // =========================
              Text(
                'Your pharmacy account has been submitted successfully.\n\n'
                'Our team is currently reviewing your pharmacy documents and verification information.',

                textAlign: TextAlign.center,

                style: TextStyle(
                  fontSize: 16,

                  height: 1.6,

                  color: AppColors.secondaryText,
                ),
              ),

              const SizedBox(height: 20),

              // =========================
              // STATUS CARD
              // =========================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: BoxDecoration(
                  color: AppColors.white,

                  borderRadius: BorderRadius.circular(20),

                  border: Border.all(color: AppColors.pendingOrange),
                ),

                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,

                  children: [
                    const Icon(
                      Icons.pending_actions,

                      color: AppColors.pendingOrange,
                    ),

                    const SizedBox(width: 10),

                    const Text(
                      'Pending Approval',

                      style: TextStyle(
                        fontSize: 16,

                        fontWeight: FontWeight.w600,

                        color: AppColors.darkGreen,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 40),

              // =========================
              // BUTTON
              // =========================
              CustomButton(
                text: 'Back To Login',

                onPressed: () {
                  Navigator.pushReplacement(
                    context,

                    MaterialPageRoute(builder: (_) => const LoginPage()),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
