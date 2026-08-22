import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../employee_workspace/domain/employee_task.dart';
import '../../../employee_workspace/presentation/cubit/employee_workspace_cubit.dart';
import '../../../employee_workspace/presentation/cubit/employee_workspace_state.dart';
import '../../../employee_workspace/presentation/pages/employee_account_page.dart';
import '../../../support/presentation/pages/support_page.dart';
import '../../../notifications/presentation/cubit/notifications_cubit.dart';
import '../../../notifications/presentation/cubit/notifications_state.dart';
import '../../../notifications/presentation/pages/notifications_page.dart';
import '../../data/models/auth_session_model.dart';
import '../cubit/auth_cubit.dart';
import '../../../../core/format/money.dart';

class EmployeeSessionPage extends StatefulWidget {
  final AuthSession session;

  const EmployeeSessionPage({super.key, required this.session});

  @override
  State<EmployeeSessionPage> createState() => _EmployeeSessionPageState();
}

class _EmployeeSessionPageState extends State<EmployeeSessionPage> {
  @override
  void initState() {
    super.initState();
    context.read<EmployeeWorkspaceCubit>().load(widget.session.actor.id);
  }

  @override
  Widget build(BuildContext context) {
    final actor = widget.session.actor;
    final pharmacy = widget.session.activePharmacy;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Employee Session'),
        actions: [
          BlocBuilder<NotificationsCubit, NotificationsState>(
            builder: (context, notifications) {
              final unread = notifications.unreadCount;
              return IconButton(
                tooltip: 'Notifications',
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const NotificationsPage()),
                ),
                icon: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    const Icon(Icons.notifications),
                    if (unread > 0)
                      Positioned(
                        right: -4,
                        top: -4,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.errorRed,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            notifications.badgeLabel,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              );
            },
          ),
          IconButton(
            tooltip: 'My account',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) =>
                    EmployeeAccountPage(actor: widget.session.actor),
              ),
            ),
            icon: const Icon(Icons.manage_accounts),
          ),
          IconButton(
            tooltip: 'Support',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const SupportPage()),
            ),
            icon: const Icon(Icons.support_agent),
          ),
          IconButton(
            tooltip: 'Logout',
            onPressed: () => context.read<AuthCubit>().logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: BlocBuilder<EmployeeWorkspaceCubit, EmployeeWorkspaceState>(
        builder: (context, state) {
          return RefreshIndicator(
            onRefresh: () =>
                context.read<EmployeeWorkspaceCubit>().load(actor.id),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _profileCard(context, actor, pharmacy),
                const SizedBox(height: 16),
                ..._workspace(context, state, actor.id),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _profileCard(
    BuildContext context,
    AuthActor actor,
    SessionPharmacy? pharmacy,
  ) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(actor.name, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(actor.email),
            const SizedBox(height: 8),
            Text('Role: ${actor.role}'),
            Text('Status: ${actor.status ?? 'active'}'),
            if (pharmacy != null) ...[
              const Divider(height: 32),
              Text('Pharmacy: ${pharmacy.name}'),
              Text(pharmacy.address),
            ],
          ],
        ),
      ),
    );
  }

  List<Widget> _workspace(
    BuildContext context,
    EmployeeWorkspaceState state,
    int employeeId,
  ) {
    if (state.status == EmployeeWorkspaceStatus.loading ||
        state.status == EmployeeWorkspaceStatus.initial) {
      return const [
        Padding(
          padding: EdgeInsets.symmetric(vertical: 32),
          child: Center(child: CircularProgressIndicator()),
        ),
      ];
    }

    if (state.status == EmployeeWorkspaceStatus.failure) {
      return [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                Text(
                  userFacingError(
                    state.error,
                    fallback: 'Unable to load your workspace.',
                  ),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.errorRed),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  key: const ValueKey('employee-workspace-retry-button'),
                  onPressed: () =>
                      context.read<EmployeeWorkspaceCubit>().load(employeeId),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Retry'),
                ),
              ],
            ),
          ),
        ),
      ];
    }

    return [
      _salesCard(state),
      const SizedBox(height: 16),
      _tasksCard(context, state, employeeId),
    ];
  }

  Widget _salesCard(EmployeeWorkspaceState state) {
    final sales = state.sales;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'My Sales',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _metric(
                    'Sales',
                    sales.totalSales.toString(),
                    Colors.blue,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _metric(
                    'Revenue',
                    money(sales.totalPrice),
                    AppColors.lightGreen,
                  ),
                ),
              ],
            ),
            if (sales.sales.isNotEmpty) ...[
              const Divider(height: 28),
              const Text(
                'Recent',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              ...sales.sales
                  .take(5)
                  .map(
                    (sale) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Invoice #${sale.id}'),
                          Text(
                            money(sale.totalPrice),
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              color: Colors.green,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
            ] else ...[
              const SizedBox(height: 10),
              const Text('No sales recorded yet.'),
            ],
          ],
        ),
      ),
    );
  }

  Widget _tasksCard(
    BuildContext context,
    EmployeeWorkspaceState state,
    int employeeId,
  ) {
    final tasks = state.tasks;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'My Tasks',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                Text(
                  '${tasks.pendingCount} pending / ${tasks.doneCount} done',
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (tasks.tasks.isEmpty)
              const Text('No tasks assigned yet.')
            else
              ...tasks.tasks.map(
                (task) => _taskTile(context, state, task, employeeId),
              ),
          ],
        ),
      ),
    );
  }

  Widget _taskTile(
    BuildContext context,
    EmployeeWorkspaceState state,
    EmployeeTask task,
    int employeeId,
  ) {
    final busy = state.mutatingTaskId == task.id;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      dense: true,
      leading: Icon(
        task.isDone ? Icons.check_circle : Icons.radio_button_unchecked,
        color: task.isDone ? AppColors.lightGreen : Colors.grey,
      ),
      title: Text(
        task.title,
        style: TextStyle(
          decoration: task.isDone ? TextDecoration.lineThrough : null,
        ),
      ),
      subtitle: _taskSubtitle(task),
      trailing: task.isDone
          ? Text(task.statusLabel, style: const TextStyle(fontSize: 12))
          : TextButton(
              onPressed: state.mutatingTaskId != null
                  ? null
                  : () => _markDone(context, task, employeeId),
              child: busy
                  ? const SizedBox.square(
                      dimension: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Mark done'),
            ),
    );
  }

  Widget? _taskSubtitle(EmployeeTask task) {
    final created = task.createdAt;
    final parts = <String>[
      if (task.description != null) task.description!,
      if (created != null)
        'Assigned ${created.year.toString().padLeft(4, '0')}-'
            '${created.month.toString().padLeft(2, '0')}-'
            '${created.day.toString().padLeft(2, '0')}',
    ];
    if (parts.isEmpty) return null;
    return Text(parts.join(' - '), style: const TextStyle(fontSize: 12));
  }

  Future<void> _markDone(
    BuildContext context,
    EmployeeTask task,
    int employeeId,
  ) async {
    final cubit = context.read<EmployeeWorkspaceCubit>();
    final messenger = ScaffoldMessenger.of(context);

    final ok = await cubit.markTaskDone(employeeId, task.id);

    if (!ok) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            userFacingError(
              cubit.state.error,
              fallback: 'The task could not be updated.',
            ),
          ),
        ),
      );
    }
  }

  Widget _metric(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(label),
        ],
      ),
    );
  }
}
