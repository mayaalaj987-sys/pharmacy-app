abstract class AuthState {}

class AuthInitial extends AuthState {}

class AuthLoading extends AuthState {}

class AuthSuccess extends AuthState {} // للـ Login فقط

class PharmacistRegisterSuccess extends AuthState {
  final int pharmacistId;
  PharmacistRegisterSuccess(this.pharmacistId);
}

class PharmacyRegisterSuccess extends AuthState {} // جديد - للـ registerPharmacy

class AuthError extends AuthState {
  final String message;
  AuthError(this.message);
}