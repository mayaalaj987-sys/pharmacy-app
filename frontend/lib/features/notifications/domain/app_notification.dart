/// An in-app notification produced by the backend `notifications` table.
///
/// The backend stores its `title`/`message` in Arabic for the older event
/// types. The app UI is English-only, so [displayTitle] and [displayMessage]
/// resolve English copy from the machine-readable [type] whenever a known
/// mapping exists, and only fall back to the stored text when it is already
/// ASCII (newer events are authored in English server-side).
class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type;
  final bool isRead;
  final DateTime? date;
  final DateTime? createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.isRead,
    this.date,
    this.createdAt,
  });

  static const _titlesByType = <String, String>{
    'pharmacy_approved': 'Pharmacy approved',
    'pharmacy_rejected': 'Pharmacy rejected',
    'employee_approved': 'Employee approved',
    'employee': 'Employee update',
    'order': 'Purchase order update',
    'sale': 'Sale completed',
    'task': 'Task update',
    'low_stock': 'Low stock',
    'out_of_stock': 'Out of stock',
    'pharmacist': 'Account update',
    'medicine': 'Medicine update',
  };

  static const _messagesByType = <String, String>{
    'employee': 'An employee record in your pharmacy was updated.',
    'order': 'A purchase order status has changed.',
    'sale': 'A new sale was recorded.',
    'task': 'A task was created or completed.',
    'low_stock': 'A medicine has reached its reorder level.',
    'out_of_stock': 'A medicine is now out of stock.',
    'pharmacist': 'Your account was updated.',
    'medicine': 'A medicine record was updated.',
  };

  /// English title for display. Never returns non-ASCII text.
  String get displayTitle {
    if (_isAscii(title) && title.trim().isNotEmpty) return title;
    return _titlesByType[type] ?? 'Notification';
  }

  /// English message for display. Never returns non-ASCII text.
  String get displayMessage {
    if (_isAscii(message) && message.trim().isNotEmpty) return message;
    return _messagesByType[type] ?? 'You have a new notification.';
  }

  static bool _isAscii(String value) => !value.runes.any((r) => r > 127);

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    int toInt(dynamic v) =>
        v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;
    DateTime? toDate(dynamic v) {
      final raw = v?.toString();
      if (raw == null || raw.isEmpty) return null;
      return DateTime.tryParse(raw);
    }

    final rawRead = json['is_read'];

    return AppNotification(
      id: toInt(json['id']),
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      isRead: rawRead is bool
          ? rawRead
          : (rawRead?.toString() == '1' || rawRead?.toString() == 'true'),
      date: toDate(json['date']),
      createdAt: toDate(json['created_at']),
    );
  }
}
