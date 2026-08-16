import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/inventory_repository.dart';
import 'inventory_state.dart';

class InventoryCubit extends Cubit<InventoryState> {
  final InventoryRepository repository;

  InventoryCubit(this.repository) : super(const InventoryState.initial());

  Future<void> load() async {
    if (state.status == InventoryStatus.loading) return;
    emit(state.copyWith(status: InventoryStatus.loading, clearError: true));
    try {
      final medicines = await repository.fetchMedicines();
      emit(
        state.copyWith(status: InventoryStatus.ready, medicines: medicines),
      );
    } on AuthApiException catch (error) {
      emit(state.copyWith(status: InventoryStatus.failure, error: error));
    } catch (_) {
      emit(
        state.copyWith(
          status: InventoryStatus.failure,
          error: const AuthApiException(message: 'Unable to load medicines.'),
        ),
      );
    }
  }

  Future<bool> addMedicine(Map<String, dynamic> payload) {
    return _mutate(() => repository.addMedicine(payload));
  }

  Future<bool> editMedicine(int id, Map<String, dynamic> payload) {
    return _mutate(() => repository.editMedicine(id, payload));
  }

  /// Runs a write, then reloads authoritative server state so the list never
  /// diverges from the backend. Single-flight via [InventoryState.saving].
  Future<bool> _mutate(Future<void> Function() action) async {
    if (state.saving) return false;
    emit(state.copyWith(saving: true, clearError: true));
    try {
      await action();
      final medicines = await repository.fetchMedicines();
      emit(
        state.copyWith(
          status: InventoryStatus.ready,
          medicines: medicines,
          saving: false,
        ),
      );
      return true;
    } on AuthApiException catch (error) {
      emit(state.copyWith(saving: false, error: error));
      return false;
    } catch (_) {
      emit(
        state.copyWith(
          saving: false,
          error: const AuthApiException(message: 'Unable to save medicine.'),
        ),
      );
      return false;
    }
  }
}
