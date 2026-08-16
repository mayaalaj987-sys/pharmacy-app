import '../../../auth/data/models/auth_api_exception.dart';
import '../../domain/supplier.dart';

enum SuppliersStatus { initial, loading, ready, failure }

class SuppliersState {
  final SuppliersStatus status;
  final List<Supplier> suppliers;
  final AuthApiException? error;

  /// Catalogue medicines per supplier id, populated on demand.
  final Map<int, List<SupplierMedicine>> medicinesBySupplier;
  final int? loadingMedicinesFor;
  final AuthApiException? medicinesError;

  const SuppliersState({
    this.status = SuppliersStatus.initial,
    this.suppliers = const <Supplier>[],
    this.error,
    this.medicinesBySupplier = const <int, List<SupplierMedicine>>{},
    this.loadingMedicinesFor,
    this.medicinesError,
  });

  const SuppliersState.initial() : this();

  SuppliersState copyWith({
    SuppliersStatus? status,
    List<Supplier>? suppliers,
    AuthApiException? error,
    Map<int, List<SupplierMedicine>>? medicinesBySupplier,
    int? loadingMedicinesFor,
    AuthApiException? medicinesError,
    bool clearError = false,
    bool clearMedicinesLoading = false,
    bool clearMedicinesError = false,
  }) {
    return SuppliersState(
      status: status ?? this.status,
      suppliers: suppliers ?? this.suppliers,
      error: clearError ? null : (error ?? this.error),
      medicinesBySupplier: medicinesBySupplier ?? this.medicinesBySupplier,
      loadingMedicinesFor: clearMedicinesLoading
          ? null
          : (loadingMedicinesFor ?? this.loadingMedicinesFor),
      medicinesError: clearMedicinesError
          ? null
          : (medicinesError ?? this.medicinesError),
    );
  }
}
