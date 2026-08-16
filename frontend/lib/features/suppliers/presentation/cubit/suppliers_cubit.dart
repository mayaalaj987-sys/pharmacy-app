import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/suppliers_repository.dart';
import '../../domain/supplier.dart';
import 'suppliers_state.dart';

class SuppliersCubit extends Cubit<SuppliersState> {
  final SuppliersRepository repository;

  SuppliersCubit(this.repository) : super(const SuppliersState.initial());

  Future<void> load() async {
    if (state.status == SuppliersStatus.loading) return;
    emit(state.copyWith(status: SuppliersStatus.loading, clearError: true));
    try {
      final suppliers = await repository.fetchSuppliers();
      emit(
        state.copyWith(status: SuppliersStatus.ready, suppliers: suppliers),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: SuppliersStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: SuppliersStatus.failure,
          error: const AuthApiException(message: 'Unable to load suppliers.'),
        ),
      );
    }
  }

  Future<void> loadMedicines(int supplierId) async {
    if (state.loadingMedicinesFor == supplierId) return;
    emit(
      state.copyWith(
        loadingMedicinesFor: supplierId,
        clearMedicinesError: true,
      ),
    );
    try {
      final medicines = await repository.fetchSupplierMedicines(supplierId);
      emit(
        state.copyWith(
          medicinesBySupplier: {
            ...state.medicinesBySupplier,
            supplierId: medicines,
          },
          clearMedicinesLoading: true,
        ),
      );
    } on AuthApiException catch (error) {
      emit(
        state.copyWith(
          medicinesError: error,
          clearMedicinesLoading: true,
        ),
      );
    } catch (_) {
      emit(
        state.copyWith(
          medicinesError: const AuthApiException(
            message: 'Unable to load supplier medicines.',
          ),
          clearMedicinesLoading: true,
        ),
      );
    }
  }

  List<SupplierMedicine>? medicinesFor(int supplierId) =>
      state.medicinesBySupplier[supplierId];
}
