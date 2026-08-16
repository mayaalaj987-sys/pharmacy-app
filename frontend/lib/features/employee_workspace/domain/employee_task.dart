/// A task assigned to the signed-in employee (`tasks` table).
/// Backend statuses: pending | done
class EmployeeTask {
  final int id;
  final String title;
  final String? description;
  final String status;
  final DateTime? createdAt;

  const EmployeeTask({
    required this.id,
    required this.title,
    required this.status,
    this.description,
    this.createdAt,
  });

  bool get isDone => status == 'done';

  String get statusLabel => isDone ? 'Done' : 'Pending';

  factory EmployeeTask.fromJson(Map<String, dynamic> json) {
    int toInt(dynamic v) =>
        v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
    final description = json['description']?.toString();
    final created = json['created_at']?.toString();

    return EmployeeTask(
      id: toInt(json['id']),
      title: json['title']?.toString() ?? '',
      description:
          description == null || description.isEmpty ? null : description,
      status: json['status']?.toString() ?? 'pending',
      createdAt: created == null || created.isEmpty
          ? null
          : DateTime.tryParse(created),
    );
  }
}
