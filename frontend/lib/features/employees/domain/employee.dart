import '../../employee_offers/domain/party_rating.dart';

/// Employee as returned by `SafeEmployeeResource`.
/// Credentials and recruitment documents are never exposed by the backend.
class Employee {
  final int id;
  final int? pharmacyId;
  final String name;
  /// Empty for anyone in the hiring pool: contact details are withheld until
  /// this pharmacy has hired them. A CV is how you evaluate a stranger.
  final String phone;
  final String email;

  /// Whether this applicant has uploaded each document. Availability only —
  /// reading a file is a separate, logged request.
  final bool hasCv;
  final bool hasExperienceProof;

  /// The status of *this* pharmacy's offer to them, if it has made one. Never
  /// another pharmacy's: the backend scopes it to the caller.
  final String? offerStatus;

  /// Which shift this pharmacy offered them, so two outstanding offers are
  /// distinguishable at a glance.
  final String? offerShift;

  /// How previous employers rated their work. A name and a CV say what someone
  /// claims; this says how it went for the people who found out.
  final PartyRating rating;

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
    this.hasCv = false,
    this.hasExperienceProof = false,
    this.offerStatus,
    this.offerShift,
    this.rating = const PartyRating(),
    this.createdAt,
  });

  bool get isTrainee => role == 'trainee';

  /// Whether the backend disclosed a way to reach this person. False for
  /// anyone in the pool, true once they work here.
  bool get hasContactDetails => phone.isNotEmpty || email.isNotEmpty;

  String get roleLabel => switch (role) {
        'employee' => 'Employee',
        'trainee' => 'Trainee',
        _ => role,
      };

  String? get offerShiftLabel => switch (offerShift) {
    'morning' => 'Morning',
    'evening' => 'Evening',
    _ => null,
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
      hasCv: json['has_cv'] == true,
      hasExperienceProof: json['has_experience_proof'] == true,
      offerStatus: json['offer'] is Map<String, dynamic>
          ? (json['offer'] as Map<String, dynamic>)['status']?.toString()
          : null,
      offerShift: json['offer'] is Map<String, dynamic>
          ? (json['offer'] as Map<String, dynamic>)['shift']?.toString()
          : null,
      rating: PartyRating.fromJson(json['rating'] as Map<String, dynamic>?),
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
