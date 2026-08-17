/// A support request the user raised, with the administrator's answer if any.
class SupportTicket {
  final int id;
  final String subject;
  final String message;
  final String status;
  final String? adminResponse;
  final DateTime? respondedAt;
  final DateTime? createdAt;

  const SupportTicket({
    required this.id,
    required this.subject,
    required this.message,
    required this.status,
    this.adminResponse,
    this.respondedAt,
    this.createdAt,
  });

  bool get isOpen => status == 'open';

  bool get hasResponse => (adminResponse ?? '').trim().isNotEmpty;

  String get statusLabel => isOpen ? 'Awaiting reply' : 'Answered';

  static DateTime? _date(dynamic value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) return null;

    return DateTime.tryParse(raw);
  }

  factory SupportTicket.fromJson(Map<String, dynamic> json) {
    return SupportTicket(
      id: json['id'] is num
          ? (json['id'] as num).toInt()
          : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      subject: json['subject']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      status: json['status']?.toString() ?? 'open',
      adminResponse: json['admin_response']?.toString(),
      respondedAt: _date(json['responded_at']),
      createdAt: _date(json['created_at']),
    );
  }
}
