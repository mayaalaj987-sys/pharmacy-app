import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../employees/domain/employee.dart';
import '../../../employees/presentation/cubit/employees_cubit.dart';
import '../../domain/pharmacy_task.dart';
import '../cubit/tasks_cubit.dart';
import '../cubit/tasks_state.dart';

class TasksPage extends StatefulWidget {
  const TasksPage({super.key});

  @override
  State<TasksPage> createState() => _TasksPageState();
}

class _TasksPageState extends State<TasksPage> {
  int? _pharmacyId;

  /// Client-side view filter over the server-provided task list.
  String _statusFilter = 'all';

  @override
  void initState() {
    super.initState();
    _pharmacyId = context.read<AuthCubit>().session?.activePharmacy?.id;
    context.read<TasksCubit>().load();
    // Employees are needed to assign a task; the backend only accepts an
    // approved employee of this pharmacy.
    final id = _pharmacyId;
    if (id != null) context.read<EmployeesCubit>().load(id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: "Tasks"),
      ),

      floatingActionButton: BlocBuilder<TasksCubit, TasksState>(
        builder: (context, state) {
          return FloatingActionButton(
            onPressed: state.busy ? null : () => _createTaskFlow(context),
            backgroundColor: AppColors.darkGreen,
            child: const Icon(Icons.add, color: Colors.white),
          );
        },
      ),

      body: BlocBuilder<TasksCubit, TasksState>(
        builder: (context, state) => _body(context, state),
      ),
    );
  }

  Widget _body(BuildContext context, TasksState state) {
    if (state.status == TasksStatus.loading ||
        state.status == TasksStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == TasksStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(state.error, fallback: 'Unable to load tasks.'),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('tasks-retry-button'),
                onPressed: () => context.read<TasksCubit>().load(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final tasks = state.tasks;
    final visibleTasks = _statusFilter == 'all'
        ? tasks.tasks
        : tasks.tasks.where((t) => t.status == _statusFilter).toList();

    return RefreshIndicator(
      onRefresh: () => context.read<TasksCubit>().load(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: _metric(
                  'Pending',
                  tasks.pendingCount.toString(),
                  AppColors.pendingOrange,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _metric(
                  'Done',
                  tasks.doneCount.toString(),
                  AppColors.lightGreen,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final entry in const [
                  ['all', 'All'],
                  ['pending', 'Pending'],
                  ['done', 'Completed'],
                ])
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(entry[1]),
                      selected: _statusFilter == entry[0],
                      onSelected: (_) =>
                          setState(() => _statusFilter = entry[0]),
                    ),
                  ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          if (visibleTasks.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 32),
              child: Center(
                child: Text(
                  _statusFilter == 'all'
                      ? "No tasks created yet"
                      : "No ${_statusFilter == 'done' ? 'completed' : 'pending'} tasks",
                ),
              ),
            )
          else
            ...visibleTasks.map((task) => _taskCard(context, state, task)),
        ],
      ),
    );
  }

  Widget _metric(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 22,
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

  Widget _taskCard(BuildContext context, TasksState state, PharmacyTask task) {
    final busy = state.deletingTaskId == task.id;

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
                    task.title,
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      decoration: task.isDone
                          ? TextDecoration.lineThrough
                          : null,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color:
                        (task.isDone
                                ? AppColors.lightGreen
                                : AppColors.pendingOrange)
                            .withValues(alpha: .15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    task.statusLabel,
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
              ],
            ),
            if (task.description != null) ...[
              const SizedBox(height: 6),
              Text(
                task.description!,
                style: const TextStyle(fontSize: 13, color: Colors.grey),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.person_outline, size: 16, color: Colors.grey),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    task.employeeName,
                    style: const TextStyle(fontSize: 13),
                  ),
                ),
                if (task.createdAt != null)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: Text(
                      _formatDate(task.createdAt!),
                      style: const TextStyle(fontSize: 11, color: Colors.grey),
                    ),
                  ),
                TextButton.icon(
                  onPressed: state.busy
                      ? null
                      : () => _confirmDelete(context, task),
                  icon: busy
                      ? const SizedBox.square(
                          dimension: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.delete_outline, size: 18),
                  label: const Text('Delete'),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.errorRed,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  Future<void> _createTaskFlow(BuildContext context) async {
    final tasksCubit = context.read<TasksCubit>();
    final messenger = ScaffoldMessenger.of(context);
    final employees = context.read<EmployeesCubit>().state.current;

    if (employees.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Hire an employee before assigning tasks. Tasks can only be '
            'assigned to approved employees of this pharmacy.',
          ),
        ),
      );
      return;
    }

    final result = await showDialog<_NewTask>(
      context: context,
      builder: (_) => _NewTaskDialog(employees: employees),
    );

    if (result == null) return;

    final ok = await tasksCubit.createTask(
      employeeId: result.employeeId,
      title: result.title,
      description: result.description,
    );

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Task assigned successfully.'
              : userFacingError(
                  tasksCubit.state.error,
                  fallback: 'The task could not be created.',
                ),
        ),
      ),
    );
  }

  Future<void> _confirmDelete(BuildContext context, PharmacyTask task) async {
    final tasksCubit = context.read<TasksCubit>();
    final messenger = ScaffoldMessenger.of(context);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete task?'),
        content: Text('"${task.title}" will be removed permanently.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: TextButton.styleFrom(foregroundColor: AppColors.errorRed),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    final ok = await tasksCubit.deleteTask(task.id);

    if (!ok) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            userFacingError(
              tasksCubit.state.error,
              fallback: 'The task could not be deleted.',
            ),
          ),
        ),
      );
    }
  }
}

class _NewTask {
  final int employeeId;
  final String title;
  final String? description;

  const _NewTask({
    required this.employeeId,
    required this.title,
    this.description,
  });
}

class _NewTaskDialog extends StatefulWidget {
  final List<Employee> employees;

  const _NewTaskDialog({required this.employees});

  @override
  State<_NewTaskDialog> createState() => _NewTaskDialogState();
}

class _NewTaskDialogState extends State<_NewTaskDialog> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  late int _employeeId = widget.employees.first.id;
  String? _titleError;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('New Task'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<int>(
              key: const ValueKey('task-employee-dropdown'),
              initialValue: _employeeId,
              decoration: const InputDecoration(
                labelText: 'Assign to',
                border: OutlineInputBorder(),
              ),
              items: widget.employees
                  .map(
                    (employee) => DropdownMenuItem(
                      value: employee.id,
                      child: Text(employee.name),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                if (value != null) setState(() => _employeeId = value);
              },
            ),
            const SizedBox(height: 14),
            TextField(
              key: const ValueKey('task-title-field'),
              controller: _titleController,
              maxLength: 255,
              decoration: InputDecoration(
                labelText: 'Title',
                errorText: _titleError,
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 6),
            TextField(
              key: const ValueKey('task-description-field'),
              controller: _descriptionController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Description (optional)',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        TextButton(
          onPressed: () {
            final title = _titleController.text.trim();
            if (title.isEmpty) {
              setState(() => _titleError = 'A task title is required.');
              return;
            }
            Navigator.pop(
              context,
              _NewTask(
                employeeId: _employeeId,
                title: title,
                description: _descriptionController.text,
              ),
            );
          },
          child: const Text('Assign'),
        ),
      ],
    );
  }
}
