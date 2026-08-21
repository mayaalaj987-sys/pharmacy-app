import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../notifications/presentation/cubit/notifications_cubit.dart';
import '../../data/models/auth_session_model.dart';
import 'employee_session_page.dart';
import 'medicine_page.dart';
import 'pos_page.dart';

/// Employee workspace.
///
/// Exposes only what the backend authorises for the `employee` guard:
/// read-only medicines, POS (`POST /sale/create`), plus the employee's own
/// sales, tasks and notifications on the Home tab. Pharmacist-only management
/// (employees, pharmacy profile, suppliers, purchases, reports, admin) is
/// intentionally absent — the backend remains the source of truth.
class EmployeeNavigationPage extends StatefulWidget {
  final AuthSession session;

  const EmployeeNavigationPage({super.key, required this.session});

  @override
  State<EmployeeNavigationPage> createState() => _EmployeeNavigationPageState();
}

class _EmployeeNavigationPageState extends State<EmployeeNavigationPage> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    // Populates the app-bar notification badge from the employee's own bell,
    // not the pharmacy-scoped feed — that one carries the owner's traffic and
    // misses the employee's personal notifications entirely.
    context.read<NotificationsCubit>().refreshQuietlyForEmployee();
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      EmployeeSessionPage(session: widget.session),
      const MedicinesPage(readOnly: true),
      const PosPage(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: (value) => setState(() => _index = value),
        selectedItemColor: AppColors.darkGreen,
        unselectedItemColor: Colors.grey,
        type: BottomNavigationBarType.fixed,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(
            icon: Icon(Icons.medication),
            label: 'Medicines',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.point_of_sale),
            label: 'POS',
          ),
        ],
      ),
    );
  }
}
