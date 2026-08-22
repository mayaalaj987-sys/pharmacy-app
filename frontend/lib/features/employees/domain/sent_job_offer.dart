class SentJobOffer {
  const SentJobOffer({
    required this.id,
    required this.status,
    required this.shift,
    required this.applicantName,
    required this.applicantRole,
    this.salary,
    this.offeredAt,
    this.respondedAt,
  });

  final int id;
  final String status;
  final String shift;
  final String applicantName;
  final String applicantRole;
  final double? salary;
  final DateTime? offeredAt;
  final DateTime? respondedAt;

  bool get canWithdraw => status == 'pending';

  factory SentJobOffer.fromJson(Map<String, dynamic> json) {
    final applicant = json['applicant'];
    final salary = json['salary'];
    return SentJobOffer(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      status: json['status']?.toString() ?? 'pending',
      shift: json['shift']?.toString() ?? '',
      applicantName: applicant is Map
          ? applicant['name']?.toString() ?? 'Applicant'
          : 'Applicant',
      applicantRole: applicant is Map
          ? applicant['role']?.toString() ?? ''
          : '',
      salary: salary == null ? null : double.tryParse(salary.toString()),
      offeredAt: DateTime.tryParse(json['offered_at']?.toString() ?? ''),
      respondedAt: DateTime.tryParse(json['responded_at']?.toString() ?? ''),
    );
  }
}
