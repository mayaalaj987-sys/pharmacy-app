import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/medicine.dart';

enum InventoryStatus { initial, loading, ready, failure }

class InventoryState {
  final InventoryStatus status;
  final List<Medicine> medicines;
  final AuthApiException? error;

  /// True while an add/edit mutation is in flight (list stays visible).
  final bool saving;

  const InventoryState({
    this.status = InventoryStatus.initial,
    this.medicines = const <Medicine>[],
    this.error,
    this.saving = false,
  });

  const InventoryState.initial() : this();

  bool get isEmpty => status == InventoryStatus.ready && medicines.isEmpty;

  InventoryState copyWith({
    InventoryStatus? status,
    List<Medicine>? medicines,
    AuthApiException? error,
    bool? saving,
    bool clearError = false,
  }) {
    return InventoryState(
      status: status ?? this.status,
      medicines: medicines ?? this.medicines,
      error: clearError ? null : (error ?? this.error),
      saving: saving ?? this.saving,
    );
  }
}
