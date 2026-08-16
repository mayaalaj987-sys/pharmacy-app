import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../../auth/data/models/auth_session_model.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../data/account_repository.dart';
import 'account_state.dart';

class AccountCubit extends Cubit<AccountState> {
  final AccountRepository repository;
  final AuthCubit authCubit;

  AccountCubit(this.repository, this.authCubit)
    : super(const AccountState.initial());

  Future<bool> updateProfile({
    required String name,
    String? phone,
    XFile? profileImage,
  }) {
    return _sessionOperation(
      AccountOperation.profile,
      'profile_updated',
      () => repository.updateProfile(
        name: name,
        phone: phone,
        profileImage: profileImage,
      ),
    );
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    if (_isLoading(AccountOperation.password)) return false;
    emit(
      const AccountState(operation: AccountOperation.password, loading: true),
    );
    try {
      await repository.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmation: confirmation,
      );
      emit(
        const AccountState(
          operation: AccountOperation.password,
          successCode: 'password_changed',
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(AccountState(operation: AccountOperation.password, error: error));
      return false;
    } catch (_) {
      emit(
        const AccountState(
          operation: AccountOperation.password,
          error: AuthApiException(message: 'Unable to change password.'),
        ),
      );
      return false;
    }
  }

  Future<bool> updatePharmacy({
    required String name,
    required String address,
    bool includeCoordinates = false,
    double? latitude,
    double? longitude,
  }) {
    return _sessionOperation(
      AccountOperation.pharmacy,
      'pharmacy_profile_updated',
      () => repository.updatePharmacy(
        name: name,
        address: address,
        includeCoordinates: includeCoordinates,
        latitude: latitude,
        longitude: longitude,
      ),
    );
  }

  Future<bool> deactivate(String currentPassword) async {
    if (_isLoading(AccountOperation.deactivation)) return false;
    emit(
      const AccountState(
        operation: AccountOperation.deactivation,
        loading: true,
      ),
    );
    try {
      await repository.deactivateAccount(currentPassword);
      emit(
        const AccountState(
          operation: AccountOperation.deactivation,
          successCode: 'account_deactivated',
        ),
      );
      authCubit.markUnauthenticated();
      return true;
    } on AuthApiException catch (error) {
      emit(
        AccountState(operation: AccountOperation.deactivation, error: error),
      );
      return false;
    } catch (_) {
      emit(
        const AccountState(
          operation: AccountOperation.deactivation,
          error: AuthApiException(message: 'Unable to deactivate account.'),
        ),
      );
      return false;
    }
  }

  void clearFeedback() => emit(const AccountState.initial());

  bool _isLoading(AccountOperation operation) =>
      state.loading && state.operation == operation;

  Future<bool> _sessionOperation(
    AccountOperation operation,
    String successCode,
    Future<AuthSession> Function() action,
  ) async {
    if (_isLoading(operation)) return false;
    emit(AccountState(operation: operation, loading: true));
    try {
      final session = await action();
      authCubit.synchronizeSession(session);
      emit(AccountState(operation: operation, successCode: successCode));
      return true;
    } on AuthApiException catch (error) {
      emit(AccountState(operation: operation, error: error));
      return false;
    } catch (_) {
      emit(
        AccountState(
          operation: operation,
          error: const AuthApiException(message: 'Unable to save changes.'),
        ),
      );
      return false;
    }
  }
}
