import '../../../auth/data/models/auth_api_exception.dart';

enum AccountOperation { profile, password, pharmacy, deactivation }

class AccountState {
  final AccountOperation? operation;
  final bool loading;
  final String? successCode;
  final AuthApiException? error;

  const AccountState({
    this.operation,
    this.loading = false,
    this.successCode,
    this.error,
  });

  const AccountState.initial() : this();
}
