import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../account/data/location_services.dart';
import '../../../account/domain/pharmacy_location_draft.dart';
import '../pages/pharmacy_location_picker_page.dart';

/// The pharmacy address, with an optional pin on the map beside it.
///
/// Typing the address stays the required path. The map is offered rather than
/// forced because it needs a location permission, a network round-trip and
/// Play Services — opening it automatically would strand anyone who declines
/// the permission or is offline in the middle of registering.
///
/// When a point is picked the reverse-geocoded address is offered as a
/// suggestion instead of overwriting what was typed: the shop's own wording
/// ("beside Al-Shifa Hospital") is often more useful to a courier than the
/// street the geocoder returns.
///
/// Shared by signup and Add Pharmacy so the two creation paths cannot drift.
class PharmacyLocationField extends StatelessWidget {
  final TextEditingController addressController;
  final double? latitude;
  final double? longitude;
  final String? suggestedAddress;
  final bool enabled;
  final ValueChanged<PharmacyLocationDraft> onLocationPicked;
  final VoidCallback onLocationCleared;
  final VoidCallback onSuggestionAccepted;

  /// Handed straight to [PharmacyLocationPickerPage], which already exposes
  /// these seams; null keeps its real Geolocator and Geocoding defaults.
  final CurrentLocationService? locationService;
  final AddressLookupService? addressLookup;

  const PharmacyLocationField({
    super.key,
    required this.addressController,
    required this.latitude,
    required this.longitude,
    required this.suggestedAddress,
    required this.onLocationPicked,
    required this.onLocationCleared,
    required this.onSuggestionAccepted,
    this.enabled = true,
    this.locationService,
    this.addressLookup,
  });

  bool get _hasPin => latitude != null && longitude != null;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          key: const ValueKey('pharmacy-address-input'),
          controller: addressController,
          enabled: enabled,
          minLines: 1,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: 'Pharmacy Address',
            prefixIcon: const Icon(Icons.location_on),
            filled: true,
            fillColor: Colors.white,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide.none,
            ),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: Text(
                _hasPin
                    ? '${latitude!.toStringAsFixed(6)}, ${longitude!.toStringAsFixed(6)}'
                    : 'No map location — optional',
                key: const ValueKey('pharmacy-picked-coordinates'),
                style: TextStyle(
                  fontSize: 12,
                  color: _hasPin ? AppColors.darkGreen : Colors.black54,
                ),
              ),
            ),
            if (_hasPin)
              TextButton(
                key: const ValueKey('clear-pharmacy-location-button'),
                onPressed: enabled ? onLocationCleared : null,
                child: const Text('Clear'),
              ),
            TextButton.icon(
              key: const ValueKey('pick-pharmacy-location-button'),
              onPressed: enabled ? () => _openMap(context) : null,
              icon: const Icon(Icons.map_outlined, size: 18),
              label: Text(_hasPin ? 'Change on Map' : 'Pick on Map'),
            ),
          ],
        ),
        if (suggestedAddress != null) ...[
          const SizedBox(height: 4),
          Card(
            color: AppColors.veryLightGreen,
            child: ListTile(
              dense: true,
              title: const Text(
                'Suggested address',
                style: TextStyle(fontSize: 13),
              ),
              subtitle: Text(suggestedAddress!),
              trailing: TextButton(
                key: const ValueKey('use-suggested-pharmacy-address-button'),
                onPressed: enabled ? onSuggestionAccepted : null,
                child: const Text('Use'),
              ),
            ),
          ),
        ],
      ],
    );
  }

  Future<void> _openMap(BuildContext context) async {
    final current = _hasPin
        ? PharmacyLocationDraft(latitude: latitude!, longitude: longitude!)
        : null;
    final picked = await Navigator.push<PharmacyLocationDraft>(
      context,
      MaterialPageRoute(
        builder: (_) => PharmacyLocationPickerPage(
          initialLocation: current,
          locationService: locationService,
          addressLookup: addressLookup,
        ),
      ),
    );
    if (picked != null) onLocationPicked(picked);
  }
}
