/// One job somebody held, from either side's point of view.
class EmploymentRecord {
  final int id;

  /// The other party: the pharmacy, or the person, depending who is looking.
  final String counterpartName;
  final String? counterpartDetail;

  final String shift;
  final double? salary;
  final DateTime? startedAt;
  final DateTime? endedAt;

  /// 'employee' if they resigned, 'pharmacy' if they were let go.
  final String? endedBy;

  final int days;

  /// Only a finished job can be judged.
  final bool canRate;

  /// This side's own verdict, so the screen shows what they already said
  /// instead of inviting a duplicate.
  final int? myRating;

  const EmploymentRecord({
    required this.id,
    required this.counterpartName,
    required this.shift,
    required this.canRate,
    required this.days,
    this.counterpartDetail,
    this.salary,
    this.startedAt,
    this.endedAt,
    this.endedBy,
    this.myRating,
  });

  bool get isRunning => endedAt == null;

  String get shiftLabel => shift == 'morning' ? 'Morning' : 'Evening';

  String get outcomeLabel {
    if (isRunning) return 'Currently working';
    return switch (endedBy) {
      'employee' => 'Resigned',
      'pharmacy' => 'Employment ended by the pharmacy',
      _ => 'Ended',
    };
  }

  /// Reads the pharmacy shape and the employee shape from the same envelope:
  /// the two history endpoints differ only in which party they name.
  factory EmploymentRecord.fromJson(Map<String, dynamic> json) {
    final pharmacy = json['pharmacy'];
    final employee = json['employee'];
    final counterpart = pharmacy is Map<String, dynamic> ? pharmacy : employee;

    int toInt(dynamic v) => v is num ? v.toInt() : 0;
    DateTime? toDate(dynamic v) {
      final raw = v?.toString();
      return raw == null || raw.isEmpty ? null : DateTime.tryParse(raw);
    }

    return EmploymentRecord(
      id: toInt(json['id']),
      counterpartName: counterpart is Map<String, dynamic>
          ? (counterpart['name']?.toString() ?? '')
          : '',
      counterpartDetail: counterpart is Map<String, dynamic>
          ? (counterpart['address'] ?? counterpart['role'])?.toString()
          : null,
      shift: json['shift']?.toString() ?? '',
      salary: json['salary'] is num ? (json['salary'] as num).toDouble() : null,
      startedAt: toDate(json['started_at']),
      endedAt: toDate(json['ended_at']),
      endedBy: json['ended_by']?.toString(),
      days: toInt(json['days']),
      canRate: json['can_rate'] == true,
      myRating: json['my_rating'] is num
          ? (json['my_rating'] as num).toInt()
          : null,
    );
  }
}
