import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/support_ticket.dart';

enum SupportStatus { initial, loading, ready, failure }

class SupportState {
  final SupportStatus status;
  final List<SupportTicket> tickets;
  final AuthApiException? error;

  /// True while a new ticket is being sent; the list stays visible.
  final bool sending;

  const SupportState({
    this.status = SupportStatus.initial,
    this.tickets = const <SupportTicket>[],
    this.error,
    this.sending = false,
  });

  const SupportState.initial() : this();

  int get openCount => tickets.where((ticket) => ticket.isOpen).length;

  SupportState copyWith({
    SupportStatus? status,
    List<SupportTicket>? tickets,
    AuthApiException? error,
    bool? sending,
    bool clearError = false,
  }) {
    return SupportState(
      status: status ?? this.status,
      tickets: tickets ?? this.tickets,
      error: clearError ? null : (error ?? this.error),
      sending: sending ?? this.sending,
    );
  }
}
