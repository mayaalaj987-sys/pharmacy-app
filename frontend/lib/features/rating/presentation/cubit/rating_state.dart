import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/rating_repository.dart';

enum RatingStatus { initial, loading, ready, failure }

class RatingState {
  final RatingStatus status;
  final AppRating rating;
  final AuthApiException? error;
  final bool submitting;

  const RatingState({
    this.status = RatingStatus.initial,
    this.rating = AppRating.empty,
    this.error,
    this.submitting = false,
  });

  const RatingState.initial() : this();

  RatingState copyWith({
    RatingStatus? status,
    AppRating? rating,
    AuthApiException? error,
    bool? submitting,
    bool clearError = false,
  }) {
    return RatingState(
      status: status ?? this.status,
      rating: rating ?? this.rating,
      error: clearError ? null : (error ?? this.error),
      submitting: submitting ?? this.submitting,
    );
  }
}
