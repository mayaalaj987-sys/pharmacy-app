import '../../data/models/auth_session_model.dart';

abstract class AuthState {
  const AuthState();
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthRestoring extends AuthState {
  const AuthRestoring();
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthAuthenticated extends AuthState {
  final AuthSession session;

  const AuthAuthenticated(this.session);
}

class AuthPharmacySelectionRequired extends AuthState {
  final AuthSession session;
  final String? errorMessage;

  const AuthPharmacySelectionRequired(this.session, {this.errorMessage});
}

class AuthAccessRestricted extends AuthState {
  final AuthSession session;

  const AuthAccessRestricted(this.session);
}

class AuthRestoreFailure extends AuthState {
  final String message;

  const AuthRestoreFailure(this.message);
}

class PharmacistRegisterSuccess extends AuthState {
  const PharmacistRegisterSuccess();
}

class EmployeeRegisterSuccess extends AuthState {
  const EmployeeRegisterSuccess();
}

class AuthError extends AuthState {
  final String message;
  final String? code;

  const AuthError(this.message, {this.code});
}
