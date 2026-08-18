import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/job_offer.dart';

enum EmployeeOffersStatus { initial, loading, ready, failure }

class EmployeeOffersState {
  final EmployeeOffersStatus status;
  final JobOfferInbox inbox;
  final AuthApiException? error;

  /// Which offer is being accepted, so only that button shows a spinner.
  final int? acceptingOfferId;

  const EmployeeOffersState({
    this.status = EmployeeOffersStatus.initial,
    this.inbox = const JobOfferInbox(),
    this.error,
    this.acceptingOfferId,
  });

  const EmployeeOffersState.initial() : this();

  List<JobOffer> get offers => inbox.offers;

  int get actionable => inbox.actionable;

  OfferEmployment? get employment => inbox.employment;

  bool get isEmployed => inbox.employment != null;

  bool get accepting => acceptingOfferId != null;

  EmployeeOffersState copyWith({
    EmployeeOffersStatus? status,
    JobOfferInbox? inbox,
    AuthApiException? error,
    int? acceptingOfferId,
    bool clearError = false,
    bool clearAccepting = false,
  }) {
    return EmployeeOffersState(
      status: status ?? this.status,
      inbox: inbox ?? this.inbox,
      error: clearError ? null : (error ?? this.error),
      acceptingOfferId: clearAccepting
          ? null
          : (acceptingOfferId ?? this.acceptingOfferId),
    );
  }
}
