import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../domain/employee.dart';
import '../cubit/employees_cubit.dart';
import '../cubit/employees_state.dart';

class EmployeesPage extends StatefulWidget {
  const EmployeesPage({super.key});

  @override
  State<EmployeesPage> createState() => _EmployeesPageState();
}

class _EmployeesPageState extends State<EmployeesPage> {
  int? _pharmacyId;

  @override
  void initState() {
    super.initState();
    _pharmacyId = context.read<AuthCubit>().session?.activePharmacy?.id;
    final id = _pharmacyId;
    if (id != null) context.read<EmployeesCubit>().load(id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Employees"),
      ),

      body: _pharmacyId == null
          ? const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Select an active pharmacy to manage employees.',
                  textAlign: TextAlign.center,
                ),
              ),
            )
          : BlocBuilder<EmployeesCubit, EmployeesState>(
              builder: (context, state) => _body(context, state),
            ),
    );
  }

  Widget _body(BuildContext context, EmployeesState state) {
    if (state.status == EmployeesStatus.loading ||
        state.status == EmployeesStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == EmployeesStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(
                  state.error,
                  fallback: 'Unable to load employees.',
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('employees-retry-button'),
                onPressed: () =>
                    context.read<EmployeesCubit>().load(_pharmacyId!),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => context.read<EmployeesCubit>().load(_pharmacyId!),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _sectionTitle(
            "Current Employees",
            "${state.current.length} of ${EmployeesState.maxApprovedEmployees}",
          ),
          const SizedBox(height: 8),

          if (state.current.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text("No employees hired yet"),
            )
          else
            ...state.current.map((e) => _currentCard(context, state, e)),

          const SizedBox(height: 24),

          _sectionTitle("Available Employment Requests", null),
          const Padding(
            padding: EdgeInsets.only(top: 4, bottom: 8),
            child: Text(
              "Open applications from the platform-wide pool, not requests sent "
              "specifically to your pharmacy.",
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ),

          if (state.atCapacity)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warningYellow.withValues(alpha: .15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                "This pharmacy has reached the maximum of 2 approved "
                "employees. Dismiss an employee before hiring another.",
                style: TextStyle(fontSize: 12),
              ),
            ),

          if (state.pending.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text("No pending employment requests"),
            )
          else
            ...state.pending.map((e) => _pendingCard(context, state, e)),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title, String? trailing) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        if (trailing != null)
          Text(trailing, style: const TextStyle(color: Colors.grey)),
      ],
    );
  }

  Widget _currentCard(
    BuildContext context,
    EmployeesState state,
    Employee employee,
  ) {
    final busy = state.mutatingEmployeeId == employee.id;

    return Card(
      color: AppColors.veryLightGreen,
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    employee.name,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.darkGreen,
                    ),
                  ),
                ),
                _chip(employee.roleLabel),
                const SizedBox(width: 6),
                _chip(employee.statusLabel),
              ],
            ),
            const SizedBox(height: 6),
            Text(employee.email, style: const TextStyle(fontSize: 13)),
            Text(employee.phone, style: const TextStyle(fontSize: 13)),
            if (employee.salary != null)
              Text(
                "Salary: \$${employee.salary!.toStringAsFixed(2)}",
                style: const TextStyle(fontSize: 13),
              ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: state.mutatingEmployeeId != null
                    ? null
                    : () => _confirmDismiss(context, employee),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 14,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.person_remove, size: 18),
                label: const Text("Dismiss"),
                style: TextButton.styleFrom(
                  foregroundColor: AppColors.errorRed,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pendingCard(
    BuildContext context,
    EmployeesState state,
    Employee employee,
  ) {
    final busy = state.mutatingEmployeeId == employee.id;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    employee.name,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                _chip(employee.roleLabel),
              ],
            ),
            const SizedBox(height: 6),
            Text(employee.email, style: const TextStyle(fontSize: 13)),
            Text(employee.phone, style: const TextStyle(fontSize: 13)),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: FilledButton.icon(
                onPressed: state.mutatingEmployeeId != null
                    ? null
                    : () => _approveFlow(context, employee),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 14,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.check, size: 18),
                label: const Text("Approve"),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.lightGreen.withValues(alpha: .15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: const TextStyle(fontSize: 11)),
    );
  }

  Future<void> _approveFlow(BuildContext context, Employee employee) async {
    final cubit = context.read<EmployeesCubit>();
    final messenger = ScaffoldMessenger.of(context);
    final salaryController = TextEditingController();

    // Trainees carry no salary on the backend, so only ask employees.
    double? salary;
    if (!employee.isTrainee) {
      final entered = await showDialog<String>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text('Approve ${employee.name}'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Enter the monthly salary for this employee.'),
              const SizedBox(height: 12),
              TextField(
                key: const ValueKey('employee-salary-field'),
                controller: salaryController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Salary',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () =>
                  Navigator.pop(dialogContext, salaryController.text.trim()),
              child: const Text('Approve'),
            ),
          ],
        ),
      );
      salaryController.dispose();

      if (entered == null) return;
      if (entered.isNotEmpty) {
        salary = double.tryParse(entered);
        if (salary == null) {
          messenger.showSnackBar(
            const SnackBar(content: Text('Enter a valid salary amount.')),
          );
          return;
        }
      }
    } else {
      salaryController.dispose();
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text('Approve ${employee.name}'),
          content: const Text('Trainees are approved without a salary.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Approve'),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
    }

    final ok = await cubit.approve(_pharmacyId!, employee.id, salary: salary);

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? '${employee.name} has been hired.'
              : userFacingError(
                  cubit.state.error,
                  context: ErrorContext.approveEmployee,
                  fallback: 'The employee could not be approved.',
                ),
        ),
      ),
    );
  }

  Future<void> _confirmDismiss(BuildContext context, Employee employee) async {
    final cubit = context.read<EmployeesCubit>();
    final messenger = ScaffoldMessenger.of(context);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Dismiss ${employee.name}?'),
        content: const Text(
          'This removes the employee from your pharmacy and revokes their '
          'access. This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: TextButton.styleFrom(foregroundColor: AppColors.errorRed),
            child: const Text('Dismiss'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    final ok = await cubit.dismiss(_pharmacyId!, employee.id);

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? '${employee.name} has been dismissed.'
              : userFacingError(
                  cubit.state.error,
                  context: ErrorContext.dismissEmployee,
                  fallback: 'The employee could not be dismissed.',
                ),
        ),
      ),
    );
  }
}
