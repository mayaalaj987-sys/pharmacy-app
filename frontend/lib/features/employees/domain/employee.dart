/// Employee as returned by `SafeEmployeeResource`.
/// Credentials and recruitment documents are never exposed by the backend.
class Employee {
  final int id;
  final int? pharmacyId;
  final String name;
  final String phone;
  final String email;

  /// Backend roles: employee | trainee
  final String role;

  /// Backend statuses: pending | approved
  final String status;

  /// The shift this person covers: morning | evening. Null while unattached.
  ///
  /// A pharmacy holds one person per shift, so this is also their seat — the
  /// backend enforces it with a unique index rather than a headcount.
  final String? shift;

  /// Set by the pharmacist for either role; trainees may be paid or unpaid.
  final double? salary;

  final DateTime? createdAt;

  const Employee({
    required this.id,
    required this.name,
    required this.phone,
    required this.email,
    required this.role,
    required this.status,
    this.pharmacyId,
    this.shift,
    this.salary,
    this.createdAt,
  });

  bool get isTrainee => role == 'trainee';

  String get roleLabel => switch (role) {
        'employee' => 'Employee',
        'trainee' => 'Trainee',
        _ => role,
      };

  String get shiftLabel => switch (shift) {
        'morning' => 'Morning',
        'evening' => 'Evening',
        _ => 'No shift',
      };

  String get statusLabel => switch (status) {
        'approved' => 'Approved',
        'pending' => 'Pending',
        _ => status,
      };

  factory Employee.fromJson(Map<String, dynamic> json) {
    int toInt(dynamic v) =>
        v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

    final rawSalary = json['salary'];
    final rawCreated = json['created_at']?.toString();
    final rawPharmacy = json['pharmacy_id'];

    return Employee(
      id: toInt(json['id']),
      pharmacyId: rawPharmacy == null ? null : toInt(rawPharmacy),
      name: json['name']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      shift: json['shift']?.toString(),
      salary: rawSalary == null
          ? null
          : (rawSalary is num
              ? rawSalary.toDouble()
              : double.tryParse(rawSalary.toString())),
      createdAt: rawCreated == null || rawCreated.isEmpty
          ? null
          : DateTime.tryParse(rawCreated),
    );
  }
}
