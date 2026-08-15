import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../data/datasource/auth_repository.dart';
import '../../data/models/auth_api_exception.dart';
import '../../data/models/auth_session_model.dart';
import '../../data/models/registration_status_model.dart';
import 'auth_state.dart';

class AuthCubit extends Cubit<AuthState> {
  final AuthRepository repository;
  late final StreamSubscription<void> _invalidatedSubscription;
  AuthSession? _session;
  RegistrationStatus? _registrationStatus;

  AuthCubit(this.repository) : super(const AuthInitial()) {
    _invalidatedSubscription = repository.sessionInvalidated.listen((_) {
      _session = null;
      emit(const AuthUnauthenticated());
    });
  }

  AuthSession? get session => _session;

  Future<void> restoreSession() async {
    emit(const AuthRestoring());
    try {
      final restored = await repository.restoreSession();
      if (restored == null) {
        emit(const AuthUnauthenticated());
      } else {
        _routeSession(restored);
      }
    } on AuthApiException catch (error) {
      emit(AuthRestoreFailure(error.message));
    } catch (_) {
      emit(const AuthRestoreFailure('Unable to restore your session.'));
    }
  }

  Future<void> login(
    String email,
    String password, {
    bool employee = false,
  }) async {
    emit(const AuthLoading());
    try {
      final authenticated = await repository.login(
        email,
        password,
        employee: employee,
      );
      _routeSession(authenticated);
    } on AuthApiException catch (error) {
      emit(AuthError(error.message, code: error.code));
    } catch (_) {
      emit(const AuthError('Unable to login. Please try again.'));
    }
  }

  Future<void> registerPharmacist(FormData data) async {
    emit(const AuthLoading());
    try {
      _registrationStatus = await repository.registerPharmacist(data);
      emit(PharmacistRegistrationStatus(_registrationStatus!));
    } on AuthApiException catch (error) {
      emit(AuthError(error.message, code: error.code));
    } catch (_) {
      emit(const AuthError('Unable to complete registration.'));
    }
  }

  Future<void> refreshRegistrationStatus() async {
    final current = _registrationStatus;
    if (current == null) return;

    emit(PharmacistRegistrationStatus(current, refreshing: true));
    try {
      _registrationStatus = await repository.refreshRegistrationStatus();
      emit(PharmacistRegistrationStatus(_registrationStatus!));
    } on AuthApiException catch (error) {
      emit(PharmacistRegistrationStatus(current, errorMessage: error.message));
    } catch (_) {
      emit(
        PharmacistRegistrationStatus(
          current,
          errorMessage: 'Unable to refresh your pharmacy status.',
        ),
      );
    }
  }

  Future<void> goToLoginFromRegistration() async {
    await repository.finishRegistrationStatus();
    _registrationStatus = null;
  }

  Future<void> registerEmployee(FormData data) async {
    emit(const AuthLoading());
    try {
      await repository.registerEmployee(data);
      emit(const EmployeeRegisterSuccess());
    } on AuthApiException catch (error) {
      emit(AuthError(error.message, code: error.code));
    } catch (_) {
      emit(const AuthError('Unable to complete registration.'));
    }
  }

  Future<void> selectActivePharmacy(int pharmacyId) async {
    final previous = _session;
    if (previous == null) return;

    emit(const AuthLoading());
    try {
      _routeSession(await repository.selectActivePharmacy(pharmacyId));
    } on AuthApiException catch (error) {
      emit(
        AuthPharmacySelectionRequired(previous, errorMessage: error.message),
      );
    } catch (_) {
      emit(
        AuthPharmacySelectionRequired(
          previous,
          errorMessage: 'Unable to select this pharmacy.',
        ),
      );
    }
  }

  Future<void> refreshSession() async {
    emit(const AuthLoading());
    try {
      _routeSession(await repository.refreshSession());
    } on AuthApiException catch (error) {
      if (error.statusCode == 401) {
        _session = null;
        emit(const AuthUnauthenticated());
      } else {
        emit(AuthRestoreFailure(error.message));
      }
    } catch (_) {
      emit(const AuthRestoreFailure('Unable to refresh your session.'));
    }
  }

  Future<void> logout() async {
    emit(const AuthLoading());
    try {
      await repository.logout();
    } finally {
      _session = null;
      emit(const AuthUnauthenticated());
    }
  }

  void _routeSession(AuthSession session) {
    _session = session;
    if (session.access.operational && session.activePharmacy != null) {
      emit(AuthAuthenticated(session));
    } else if (session.access.requiresActivePharmacy) {
      emit(AuthPharmacySelectionRequired(session));
    } else {
      emit(AuthAccessRestricted(session));
    }
  }

  @override
  Future<void> close() async {
    await _invalidatedSubscription.cancel();
    return super.close();
  }
}
