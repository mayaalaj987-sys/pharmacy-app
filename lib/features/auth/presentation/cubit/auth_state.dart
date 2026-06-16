abstract class AuthState {}

class AuthInitial extends AuthState {}

class AuthLoading extends AuthState {}

class AuthSuccess extends AuthState {}

/// نجاح خاص بتسجيل الـ pharmacist (الخطوة الأولى) مع رجوع الـ id
class PharmacistRegisterSuccess extends AuthState {
  final int pharmacistId;

  PharmacistRegisterSuccess(this.pharmacistId);
}

class AuthError extends AuthState {
  final String message;

  AuthError(this.message);
}