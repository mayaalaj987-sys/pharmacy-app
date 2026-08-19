import '../../features/auth/data/models/auth_api_exception.dart';

/// The app UI is English-only, but some backend operational messages are
/// authored in Arabic. This maps a failure to English user-facing wording
/// without altering the underlying error handling: the original
/// [AuthApiException] (code, statusCode, fieldErrors) is untouched and still
/// drives control flow.
///
/// Resolution order:
///   1. a known machine-readable `code`
///   2. a known HTTP status for the given [context]
///   3. the backend message, but only when it is plain ASCII
///   4. [fallback]
String userFacingError(
  AuthApiException? error, {
  required String fallback,
  ErrorContext context = ErrorContext.generic,
}) {
  if (error == null) return fallback;

  final byCode = _messagesByCode[error.code];
  if (byCode != null) return byCode;

  final byStatus = _messageForStatus(error.statusCode, context);
  if (byStatus != null) return byStatus;

  final message = error.message.trim();
  if (message.isNotEmpty && _isAscii(message)) return message;

  return fallback;
}

/// Where the failure happened, so a bare HTTP status can be explained usefully.
enum ErrorContext {
  generic,
  sendOffer,
  acceptOffer,
  dismissEmployee,
  sale,
  order,
}

const _messagesByCode = <String, String>{
  'validation_failed': 'Please check the highlighted fields and try again.',
  'unauthenticated': 'Your session has expired. Please sign in again.',
  'account_deactivated': 'This account has been deactivated.',
  'forbidden': 'You are not authorized to perform this action.',
  'active_pharmacy_required':
      'Select an active pharmacy before performing this action.',
  'active_pharmacy_forbidden':
      'You do not have access to the selected pharmacy.',
  'pharmacy_context_conflict':
      'The selected pharmacy does not match your active pharmacy.',
  'too_many_attempts': 'Too many attempts. Please wait a minute and try again.',
  'shift_taken': 'That shift is already covered at this pharmacy.',
  'employee_not_available': 'This applicant has already taken a job.',
  'offer_already_accepted': 'This offer was already accepted.',
  'offer_not_pending': 'This offer is no longer open.',
  'already_employed':
      'You already have a job. Leave it before accepting another offer.',
  'pharmacy_unavailable': 'This pharmacy is not operating right now.',
  'not_employed': 'You do not currently have a job to leave.',
  'already_an_employee': 'They are already listed as an employee.',
  'employee_not_active': 'This person does not work at your pharmacy.',
  'pharmacy_limit_reached':
      'You can run at most two pharmacies on this account.',
  'medicine_expired': 'That stock has expired and cannot be sold or bought.',
  'insufficient_stock': 'There is not enough of that on the shelf.',
  'return_window_closed':
      'Returns are only accepted within 48 hours of the sale.',
  'nothing_left_to_return': 'That line has already been returned.',
  'stock_written_off': 'The loss has been recorded.',
  'cart_empty': 'There is nothing in the cart to buy.',
  'not_a_supplier_offer': 'No supplier offers that medicine.',
  'not_the_same_drug': 'That supplier does not stock the same medicine.',
  'supplier_stock_insufficient':
      'The supplier does not have that many. Lower the quantity and try again.',
  'order_not_pending': 'This order has already been received or cancelled.',
};

String? _messageForStatus(int? status, ErrorContext context) {
  if (status == null) return null;

  return switch ((status, context)) {
    (409, ErrorContext.sendOffer) =>
      'That shift is already covered, or this applicant has taken another job.',
    (409, ErrorContext.acceptOffer) =>
      'This offer is no longer available. Pull down to refresh.',
    (400, ErrorContext.dismissEmployee) =>
      'This person does not work at your pharmacy.',
    (400, ErrorContext.sale) =>
      'One or more items do not have enough stock for this sale.',
    (400, ErrorContext.order) =>
      'This order cannot be updated in its current state.',
    (404, _) => 'The requested record was not found.',
    (409, _) => 'This action conflicts with the current state of the record.',
    (500, _) => 'The server could not complete the request. Please try again.',
    _ => null,
  };
}

bool _isAscii(String value) => !value.runes.any((rune) => rune > 127);
