import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../../account/domain/pharmacy_location_draft.dart';
import '../../../account/domain/pharmacy_edit_draft.dart';
import '../../../account/presentation/cubit/account_cubit.dart';
import '../../../account/presentation/cubit/account_state.dart';
import '../cubit/auth_cubit.dart';
import 'pharmacy_location_picker_page.dart';

class SettingsEditPharmacyPage extends StatefulWidget {
  const SettingsEditPharmacyPage({super.key});

  @override
  State<SettingsEditPharmacyPage> createState() =>
      _SettingsEditPharmacyPageState();
}

class _SettingsEditPharmacyPageState extends State<SettingsEditPharmacyPage> {
  late final TextEditingController _nameController;
  late final TextEditingController _addressController;
  double? _latitude;
  double? _longitude;
  String? _suggestedAddress;
  bool _includeCoordinates = false;

  @override
  void initState() {
    super.initState();
    final pharmacy = context.read<AuthCubit>().session!.activePharmacy!;
    _nameController = TextEditingController(text: pharmacy.name);
    _addressController = TextEditingController(text: pharmacy.address);
    _latitude = pharmacy.latitude;
    _longitude = pharmacy.longitude;
    context.read<AccountCubit>().clearFeedback();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pharmacy = context.watch<AuthCubit>().session!.activePharmacy!;
    return Scaffold(
      appBar: AppBar(title: const Text('Edit Pharmacy')),
      body: BlocBuilder<AccountCubit, AccountState>(
        builder: (context, state) {
          final loading =
              state.loading && state.operation == AccountOperation.pharmacy;
          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              TextField(
                key: const ValueKey('pharmacy-name-field'),
                controller: _nameController,
                enabled: !loading,
                decoration: _decoration(
                  'Pharmacy name',
                  error: _error(state, 'pharmacy_name'),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                key: const ValueKey('pharmacy-address-field'),
                controller: _addressController,
                enabled: !loading,
                minLines: 2,
                maxLines: 4,
                decoration: _decoration(
                  'Pharmacy address',
                  error: _error(state, 'pharmacy_address'),
                ),
              ),
              if (_suggestedAddress != null) ...[
                const SizedBox(height: 10),
                Card(
                  color: AppColors.veryLightGreen,
                  child: ListTile(
                    title: const Text('Suggested address'),
                    subtitle: Text(_suggestedAddress!),
                    trailing: TextButton(
                      key: const ValueKey('use-suggested-address-button'),
                      onPressed: loading ? null : _useSuggestedAddress,
                      child: const Text('Use Suggested Address'),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              InputDecorator(
                decoration: _decoration('Approval status'),
                child: Text(pharmacy.status.toUpperCase()),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Location',
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _latitude == null || _longitude == null
                            ? 'No location selected'
                            : '${_latitude!.toStringAsFixed(6)}, ${_longitude!.toStringAsFixed(6)}',
                        key: const ValueKey('pharmacy-draft-coordinates'),
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        children: [
                          FilledButton.icon(
                            key: const ValueKey('select-location-button'),
                            onPressed: loading ? null : _selectLocation,
                            icon: const Icon(Icons.map_outlined),
                            label: const Text('Select Location'),
                          ),
                          if (_latitude != null || _longitude != null)
                            TextButton(
                              key: const ValueKey('clear-location-button'),
                              onPressed: loading ? null : _clearLocation,
                              child: const Text('Clear Location'),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              if (state.operation == AccountOperation.pharmacy &&
                  state.error != null) ...[
                const SizedBox(height: 12),
                Text(
                  state.error!.message,
                  key: const ValueKey('pharmacy-error'),
                  style: const TextStyle(color: AppColors.errorRed),
                ),
              ],
              const SizedBox(height: 24),
              CustomButton(
                text: 'Save Changes',
                isLoading: loading,
                onPressed: loading ? null : _save,
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _selectLocation() async {
    final current = _latitude == null || _longitude == null
        ? null
        : PharmacyLocationDraft(latitude: _latitude!, longitude: _longitude!);
    final selected = await Navigator.push<PharmacyLocationDraft>(
      context,
      MaterialPageRoute(
        builder: (_) => PharmacyLocationPickerPage(initialLocation: current),
      ),
    );
    if (selected != null && mounted) {
      final draft = PharmacyEditDraft(
        address: _addressController.text,
        latitude: _latitude,
        longitude: _longitude,
        suggestedAddress: _suggestedAddress,
        includeCoordinates: _includeCoordinates,
      ).applyLocation(selected);
      setState(() {
        _latitude = draft.latitude;
        _longitude = draft.longitude;
        _suggestedAddress = draft.suggestedAddress;
        _includeCoordinates = draft.includeCoordinates;
      });
    }
  }

  void _clearLocation() {
    final draft = PharmacyEditDraft(
      address: _addressController.text,
      latitude: _latitude,
      longitude: _longitude,
      suggestedAddress: _suggestedAddress,
      includeCoordinates: _includeCoordinates,
    ).clearLocation();
    setState(() {
      _latitude = draft.latitude;
      _longitude = draft.longitude;
      _suggestedAddress = draft.suggestedAddress;
      _includeCoordinates = draft.includeCoordinates;
    });
  }

  void _useSuggestedAddress() {
    final draft = PharmacyEditDraft(
      address: _addressController.text,
      latitude: _latitude,
      longitude: _longitude,
      suggestedAddress: _suggestedAddress,
      includeCoordinates: _includeCoordinates,
    ).useSuggestedAddress();
    setState(() => _addressController.text = draft.address);
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    final address = _addressController.text.trim();
    if (name.isEmpty || address.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pharmacy name and address are required.'),
        ),
      );
      return;
    }

    final success = await context.read<AccountCubit>().updatePharmacy(
      name: name,
      address: address,
      includeCoordinates: _includeCoordinates,
      latitude: _latitude,
      longitude: _longitude,
    );
    if (success && mounted) Navigator.pop(context, true);
  }

  InputDecoration _decoration(String label, {String? error}) {
    return InputDecoration(
      labelText: label,
      errorText: error,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
    );
  }

  String? _error(AccountState state, String field) {
    if (state.operation != AccountOperation.pharmacy) return null;
    final values = state.error?.fieldErrors[field];
    return values == null || values.isEmpty ? null : values.first;
  }
}
