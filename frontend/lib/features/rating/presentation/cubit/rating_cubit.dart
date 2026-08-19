import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/rating_repository.dart';
import 'rating_state.dart';

class RatingCubit extends Cubit<RatingState> {
  final RatingRepository repository;

  RatingCubit(this.repository) : super(const RatingState.initial());

  Future<void> load() async {
    if (state.status == RatingStatus.loading) return;
    emit(state.copyWith(status: RatingStatus.loading, clearError: true));
    try {
      final rating = await repository.fetchMyRating();
      emit(state.copyWith(status: RatingStatus.ready, rating: rating));
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: RatingStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: RatingStatus.failure,
          error: const AuthApiException(message: 'Unable to load your rating.'),
        ),
      );
    }
  }

  /// Leaves or revises the rating, then re-reads it.
  ///
  /// Revisable now, where it used to be a one-shot the backend refused a second
  /// time. Holding somebody to one bad afternoon forever is not feedback.
  Future<bool> submit({
    required int pharmacistId,
    required int stars,
    String? note,
  }) async {
    if (state.submitting) return false;
    emit(state.copyWith(submitting: true, clearError: true));
    try {
      await repository.submitRating(
        pharmacistId: pharmacistId,
        stars: stars,
        note: note,
      );
      final rating = await repository.fetchMyRating();
      emit(
        state.copyWith(
          status: RatingStatus.ready,
          rating: rating,
          submitting: false,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(submitting: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          submitting: false,
          error: const AuthApiException(
            message: 'Unable to submit your rating.',
          ),
        ),
      );
      return false;
    }
  }
}
