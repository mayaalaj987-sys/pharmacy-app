import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/support_repository.dart';
import 'support_state.dart';

class SupportCubit extends Cubit<SupportState> {
  final SupportRepository repository;

  SupportCubit(this.repository) : super(const SupportState.initial());

  Future<void> load() async {
    if (state.status == SupportStatus.loading) return;
    emit(state.copyWith(status: SupportStatus.loading, clearError: true));
    try {
      emit(
        state.copyWith(
          status: SupportStatus.ready,
          tickets: await repository.fetchTickets(),
        ),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: SupportStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: SupportStatus.failure,
          error: const AuthApiException(
            message: 'Unable to load your messages.',
          ),
        ),
      );
    }
  }

  /// Sends a ticket, then reloads so the new one appears with its server id.
  Future<bool> send({required String subject, required String message}) async {
    if (state.sending) return false;
    emit(state.copyWith(sending: true, clearError: true));
    try {
      await repository.createTicket(subject: subject, message: message);
      emit(
        state.copyWith(
          sending: false,
          status: SupportStatus.ready,
          tickets: await repository.fetchTickets(),
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(sending: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          sending: false,
          error: const AuthApiException(
            message: 'Unable to send your message.',
          ),
        ),
      );
      return false;
    }
  }
}
