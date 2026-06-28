import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import 'account_type_page.dart';
import 'login_page.dart';
import 'signup_page1.dart';
import 'employee_login_page.dart';
import 'employee_signup_page.dart';

class AuthOptionsPage extends StatelessWidget {
  final AccountType accountType;

  const AuthOptionsPage({super.key, required this.accountType});

  @override
  Widget build(BuildContext context) {
    final bool isPharmacist = accountType == AccountType.pharmacist;

    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: BorderRadius.circular(30),
                ),
                child: Icon(
                  isPharmacist ? Icons.local_pharmacy : Icons.work,
                  size: 60,
                  color: AppColors.darkGreen,
                ),
              ),
              const SizedBox(height: 32),
              Text(
                isPharmacist ? 'Pharmacist' : 'Employee / Trainee',
                style: const TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.bold,
                  color: AppColors.darkGreen,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isPharmacist
                    ? 'Manage your pharmacy and team'
                    : 'Find your opportunity',
                style: const TextStyle(
                  fontSize: 15,
                  color: AppColors.secondaryText,
                ),
              ),
              const SizedBox(height: 60),
              CustomButton(
                text: 'Login',
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => isPharmacist
                          ? const LoginPage()
                          : const EmployeeLoginPage(),
                    ),
                  );
                },
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 55,
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(
                      color: AppColors.darkGreen,
                      width: 2,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => isPharmacist
                            ? const SignupPage1()
                            : const EmployeeSignupPage(),
                      ),
                    );
                  },
                  child: const Text(
                    'Sign Up',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
