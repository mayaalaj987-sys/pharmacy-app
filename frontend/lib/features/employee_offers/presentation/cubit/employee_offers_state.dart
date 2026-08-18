import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/job_offer.dart';

enum EmployeeOffersStatus { initial, loading, ready, failure }

class EmployeeOffersState {
  final EmployeeOffersStatus status;
  final JobOfferInbox inbox;
  final AuthApiException? error;

  const EmployeeOffersState({
    this.status = EmployeeOffersStatus.initial,
    this.inbox = const JobOfferInbox(),
    this.error,
  });

  const EmployeeOffersState.initial() : this();

  List<JobOffer> get offers => inbox.offers;

  int get actionable => inbox.actionable;

  OfferEmployment? get employment => inbox.employment;

  bool get isEmployed => inbox.employment != null;

  EmployeeOffersState copyWith({
    EmployeeOffersStatus? status,
    JobOfferInbox? inbox,
    AuthApiException? error,
    bool clearError = false,
  }) {
    return EmployeeOffersState(
      status: status ?? this.status,
      inbox: inbox ?? this.inbox,
      error: clearError ? null : (error ?? this.error),
    );
  }
}
