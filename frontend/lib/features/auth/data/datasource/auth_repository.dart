import 'package:dio/dio.dart';

import '../../../../core/network/auth_session_events.dart';
import '../../../../core/network/error_handler.dart';
import '../../../../core/storage/secure_storage_service.dart';
import '../models/auth_api_exception.dart';
import '../models/auth_session_model.dart';
import '../models/registration_status_model.dart';
import 'auth_remote_datasource.dart';

class AuthRepository {
  final AuthRemoteDataSource api;
  final SessionStorage storage;
  final RegistrationStatusStorage registrationStatusStorage;
  final AuthSessionEvents sessionEvents;

  AuthRepository(
    this.api,
    this.storage,
    this.registrationStatusStorage,
    this.sessionEvents,
  );

  Stream<void> get sessionInvalidated => sessionEvents.invalidated;

  Future<AuthSession> login(
    String email,
    String password, {
    bool employee = false,
  }) async {
    await storage.clearSession();

    try {
      final response = employee
          ? await api.employeeLogin(email, password)
          : await api.login(email, password);
      final result = AuthLoginResult.fromJson(
        Map<String, dynamic>.from(response.data as Map),
      );
      await storage.saveToken(result.token);
      await _persistActivePharmacy(result.session);
      return result.session;
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    } catch (_) {
      await storage.clearSession();
      rethrow;
    }
  }

  Future<AuthSession?> restoreSession() async {
    final token = await storage.getToken();
    if (token == null) {
      return null;
    }

    try {
      return await _loadRecoverableSession();
    } on AuthApiException catch (error) {
      if (error.statusCode == 401) {
        await storage.clearSession();
        return null;
      }
      rethrow;
    }
  }

  Future<AuthSession> refreshSession() => _loadRecoverableSession();

  Future<AuthSession> selectActivePharmacy(int pharmacyId) async {
    try {
      final session = await _requestSession(activePharmacyId: pharmacyId);
      if (session.activePharmacy?.id != pharmacyId ||
          !session.access.operational) {
        throw const AuthApiException(
          message: 'The selected pharmacy is not available.',
          code: 'invalid_active_pharmacy',
        );
      }
      await storage.saveActivePharmacyId(pharmacyId);
      return session;
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<RegistrationStatus> registerPharmacist(FormData data) async {
    try {
      final response = await api.registerPharmacist(data);
      final result = PharmacistRegistrationResult.fromJson(
        Map<String, dynamic>.from(response.data as Map),
      );
      await registrationStatusStorage.saveRegistrationStatusToken(
        result.statusToken,
      );
      return result.registration;
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<RegistrationStatus> refreshRegistrationStatus() async {
    final token = await registrationStatusStorage.getRegistrationStatusToken();
    if (token == null || token.isEmpty) {
      throw const AuthApiException(
        message: 'Registration status access is no longer available.',
        code: 'registration_status_unauthenticated',
        statusCode: 401,
      );
    }

    try {
      final response = await api.registrationStatus(token);
      final body = Map<String, dynamic>.from(response.data as Map);
      final responseData = Map<String, dynamic>.from(body['data'] as Map);
      return RegistrationStatus.fromJson(
        Map<String, dynamic>.from(responseData['registration'] as Map),
      );
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> finishRegistrationStatus() async {
    final token = await registrationStatusStorage.getRegistrationStatusToken();

    try {
      if (token != null && token.isNotEmpty) {
        await api.logoutRegistrationStatus(token);
      }
    } on DioException {
      // Approval should never trap the user on the status page when the
      // server cannot be reached. Only the local status credential is cleared.
    } finally {
      await registrationStatusStorage.clearRegistrationStatusToken();
    }
  }

  Future<void> registerEmployee(FormData data) async {
    try {
      await api.registerEmployee(data);
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<void> logout() async {
    try {
      await api.logout();
    } on DioException {
      // Local logout must finish even when the server is unreachable. A 401
      // already means the local token is no longer a valid server session.
    } finally {
      await storage.clearSession();
    }
  }

  Future<AuthSession> _loadRecoverableSession() async {
    try {
      var session = await _requestSession();
      if (session.access.code == 'stale_active_pharmacy') {
        await storage.clearActivePharmacy();
        session = await _requestSession();
      }
      await _persistActivePharmacy(session);
      return session;
    } on DioException catch (error) {
      throw ErrorHandler.fromDio(error);
    }
  }

  Future<AuthSession> _requestSession({int? activePharmacyId}) async {
    final response = await api.me(activePharmacyId: activePharmacyId);
    final body = Map<String, dynamic>.from(response.data as Map);
    final data = Map<String, dynamic>.from(body['data'] as Map);
    return AuthSession.fromJson(
      Map<String, dynamic>.from(data['session'] as Map),
    );
  }

  Future<void> _persistActivePharmacy(AuthSession session) async {
    final active = session.activePharmacy;
    if (active == null) {
      await storage.clearActivePharmacy();
    } else {
      await storage.saveActivePharmacyId(active.id);
    }
  }
}
