import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/network/user_facing_error.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../domain/employee.dart';
import '../cubit/employees_cubit.dart';
import '../cubit/employees_state.dart';
import '../widgets/applicant_documents_sheet.dart';
import '../../../../core/format/money.dart';

class EmployeesPage extends StatefulWidget {
  const EmployeesPage({super.key});

  @override
  State<EmployeesPage> createState() => _EmployeesPageState();
}

class _EmployeesPageState extends State<EmployeesPage> {
  /// Owned by the page rather than created per dialog.
  ///
  /// Disposing it right after `showDialog` returns crashes the app: the route
  /// is still animating out, so the TextField is still mounted and still
  /// reading from the controller. The offer had already been sent by then,
  /// which is why the failure looked like "it worked but showed a red screen".
  final _salaryController = TextEditingController();

  int? _pharmacyId;

  @override
  void initState() {
    super.initState();
    _pharmacyId = context.read<AuthCubit>().session?.activePharmacy?.id;
    final id = _pharmacyId;
    if (id != null) context.read<EmployeesCubit>().load(id);
  }

  @override
  void dispose() {
    _salaryController.dispose();
    super.dispose();
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
            "${state.current.length} of ${EmployeesState.shifts.length} shifts covered",
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
                "Every shift here is covered. Dismiss whoever holds a shift "
                "before offering it to somebody else.",
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
                _chip(employee.shiftLabel),
                const SizedBox(width: 6),
                _chip(employee.roleLabel),
              ],
            ),
            const SizedBox(height: 6),
            Text(employee.email, style: const TextStyle(fontSize: 13)),
            Text(employee.phone, style: const TextStyle(fontSize: 13)),
            if (employee.salary != null)
              Text(
                "Salary: ${money(employee.salary!)}",
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
            const SizedBox(height: 8),
            // Contact details are withheld until this pharmacy hires them, so
            // what is shown here is what a hiring decision rests on.
            Wrap(
              spacing: 6,
              children: [
                if (employee.offerStatus == 'pending')
                  _chip('Offered: ${employee.offerShiftLabel ?? 'a shift'}'),
                if (employee.hasCv) _chip('CV'),
                if (employee.hasExperienceProof) _chip('Training certificate'),
                if (!employee.hasCv && !employee.hasExperienceProof)
                  const Text(
                    'No documents uploaded',
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (employee.hasCv || employee.hasExperienceProof)
                  TextButton.icon(
                    key: ValueKey('view-documents-${employee.id}'),
                    onPressed: () => _showDocuments(context, employee),
                    icon: const Icon(Icons.description_outlined, size: 18),
                    label: const Text('Documents'),
                  ),
                const SizedBox(width: 8),
                FilledButton.icon(
                  onPressed: state.mutatingEmployeeId != null
                      ? null
                      : () => _offerFlow(context, employee),
                  icon: busy
                      ? const SizedBox.square(
                          dimension: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check, size: 18),
                  label: const Text("Send offer"),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// Opens the applicant's CV and certificate before any offer is made.
  ///
  /// Reads through the cubit's repository rather than a second instance, so
  /// there is one authenticated client and one place the base URL is decided.
  Future<void> _showDocuments(BuildContext context, Employee employee) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => ApplicantDocumentsSheet(
        employeeId: employee.id,
        applicantName: employee.name,
        repository: context.read<EmployeesCubit>().repository,
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

  /// Offers an applicant a named shift.
  ///
  /// This used to hire them outright. It cannot any more: the applicant decides,
  /// so all this does is put terms in front of them.
  ///
  /// One dialog for both roles. It used to be two — employees were asked for a
  /// salary and trainees were told they would not get one, because the backend
  /// discarded a trainee's salary in silence. It no longer does.
  Future<void> _offerFlow(BuildContext context, Employee employee) async {
    final cubit = context.read<EmployeesCubit>();
    final messenger = ScaffoldMessenger.of(context);
    final free = cubit.state.freeShifts;

    if (free.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Every shift here is already covered.')),
      );
      return;
    }

    _salaryController.clear();

    // Nothing preselected while there is a real choice. Defaulting to the first
    // free shift meant that once one was filled, the dialog quietly switched to
    // the other — so a pharmacist who thought they were offering mornings twice
    // sent an evening the second time without ever seeing it change.
    String? shift = free.length == 1 ? free.first : null;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (builderContext, setDialogState) => AlertDialog(
          title: Text('Offer ${employee.name} a job'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Which shift will they cover?',
                style: TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 8),
              // Only free shifts are offered. A covered one cannot be chosen
              // at all, rather than being chosen and then refused.
              SegmentedButton<String>(
                key: const ValueKey('employee-shift-picker'),
                segments: [
                  for (final option in EmployeesState.shifts)
                    ButtonSegment(
                      value: option,
                      label: Text(option == 'morning' ? 'Morning' : 'Evening'),
                      enabled: free.contains(option),
                    ),
                ],
                emptySelectionAllowed: true,
                selected: shift == null ? const <String>{} : {shift!},
                onSelectionChanged: (selection) =>
                    setDialogState(() => shift = selection.firstOrNull),
              ),
              if (shift == null) ...[
                const SizedBox(height: 6),
                const Text(
                  'Pick a shift to continue.',
                  style: TextStyle(fontSize: 11, color: Colors.black54),
                ),
              ],
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('employee-salary-field'),
                controller: _salaryController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Monthly salary (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: shift == null
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: const Text('Send offer'),
            ),
          ],
        ),
      ),
    );

    final entered = _salaryController.text.trim();
    if (confirmed != true || shift == null) return;

    double? salary;
    if (entered.isNotEmpty) {
      salary = double.tryParse(entered);
      if (salary == null) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Enter a valid salary amount.')),
        );
        return;
      }
    }

    final ok = await cubit.sendOffer(
      _pharmacyId!,
      employee.id,
      salary: salary,
      shift: shift!,
    );

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Offered ${employee.name} the $shift shift. They decide whether to accept.'
              : userFacingError(
                  cubit.state.error,
                  context: ErrorContext.sendOffer,
                  fallback: 'The offer could not be sent.',
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
          'They stop working here and their shift opens up. Their application '
          'returns to the hiring pool, and their tasks, sales and documents are '
          'all kept.',
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
