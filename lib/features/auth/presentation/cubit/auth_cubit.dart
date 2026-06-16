import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/error_handler.dart';
import '../../data/datasource/auth_repository.dart';
import 'auth_state.dart';

class AuthCubit extends Cubit<AuthState> {
  final AuthRepository repository;

  AuthCubit(this.repository) : super(AuthInitial());

  Future<void> login(String email, String password) async {
    try {
      emit(AuthLoading());
      await repository.login(email, password);
      emit(AuthSuccess());
    } catch (e) {
      if (e is DioException) {
        emit(AuthError(ErrorHandler.handle(e)));
      } else {
        emit(AuthError(e.toString()));
      }
    }
  }
  Future<void> registerPharmacist(FormData data) async {
    try {
      emit(AuthLoading());
      final pharmacistId = await repository.registerPharmacist(data);
      emit(PharmacistRegisterSuccess(pharmacistId));
    } catch (e) {
      if (e is DioException) {
        emit(AuthError(ErrorHandler.handle(e)));
      } else {
        emit(AuthError(e.toString()));
      }
    }
  }

  Future<void> registerPharmacy(FormData data) async {
    try {
      emit(AuthLoading());

      await repository.registerPharmacy(data);

      emit(AuthSuccess());
    } catch (e) {
      emit(AuthError(e.toString()));
    }
  }
}
