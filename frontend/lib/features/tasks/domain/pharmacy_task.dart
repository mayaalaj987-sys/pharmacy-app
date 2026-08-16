/// A task assigned by the pharmacist to one of their employees.
/// Backend statuses: pending | done
class PharmacyTask {
  final int id;
  final String title;
  final String? description;
  final String status;
  final int? employeeId;
  final String employeeName;
  final DateTime? createdAt;

  const PharmacyTask({
    required this.id,
    required this.title,
    required this.status,
    required this.employeeName,
    this.description,
    this.employeeId,
    this.createdAt,
  });

  bool get isDone => status == 'done';

  String get statusLabel => isDone ? 'Done' : 'Pending';

  factory PharmacyTask.fromJson(Map<String, dynamic> json) {
    int? toIntOrNull(dynamic v) => v == null
        ? null
        : (v is num ? v.toInt() : int.tryParse(v.toString()));

    final employee = json['employee'];
    final description = json['description']?.toString();
    final created = json['created_at']?.toString();

    return PharmacyTask(
      id: toIntOrNull(json['id']) ?? 0,
      title: json['title']?.toString() ?? '',
      description:
          description == null || description.isEmpty ? null : description,
      status: json['status']?.toString() ?? 'pending',
      employeeId: toIntOrNull(json['employee_id']),
      employeeName: employee is Map<String, dynamic>
          ? (employee['name']?.toString() ?? 'Unassigned')
          : 'Unassigned',
      createdAt: created == null || created.isEmpty
          ? null
          : DateTime.tryParse(created),
    );
  }
}
