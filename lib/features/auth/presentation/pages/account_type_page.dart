import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/account_type/account_type_card_widget.dart';
import '../../../../core/widgets/account_type/auth_header_widget.dart';
import '../../../../core/widgets/account_type/continue_button_widget.dart';
import 'auth_options_page.dart';
import 'employee_signup_page.dart';
import 'signup_page1.dart';

enum AccountType { pharmacist, employee }

class AccountTypePage extends StatefulWidget {
  const AccountTypePage({super.key});

  @override
  State<AccountTypePage> createState() => _AccountTypePageState();
}

class _AccountTypePageState extends State<AccountTypePage> {
  AccountType? selectedType;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const SizedBox(height: 40),
              const AuthHeaderWidget(),
              const SizedBox(height: 48),
              AccountTypeCardWidget(
                icon: Icons.local_pharmacy,
                title: 'Pharmacist',
                description: 'I want to manage my pharmacy',
                isSelected: selectedType == AccountType.pharmacist,
                onTap: () =>
                    setState(() => selectedType = AccountType.pharmacist),
              ),
              const SizedBox(height: 16),
              AccountTypeCardWidget(
                icon: Icons.person_search_rounded,
                title: 'Employee / Trainee',
                description: 'I am looking for a job opportunity',
                isSelected: selectedType == AccountType.employee,
                onTap: () =>
                    setState(() => selectedType = AccountType.employee),
              ),
              const SizedBox(height: 48),
              ContinueButtonWidget(
                isEnabled: selectedType != null,
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          AuthOptionsPage(accountType: selectedType!),
                    ),
                  );
                },
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}
