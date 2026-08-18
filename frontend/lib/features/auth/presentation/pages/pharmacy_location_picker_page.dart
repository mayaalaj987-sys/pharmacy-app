import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../account/data/location_services.dart';
import '../../../account/domain/pharmacy_location_draft.dart';
import '../../../account/presentation/controllers/pharmacy_location_controller.dart';

/// Places a pharmacy on the map.
///
/// Tiles come from OpenStreetMap rather than Google Maps: the Maps SDK needs
/// an API key, which needs a Google Cloud project with a billing account
/// attached, which is not available in every country this app ships to. With
/// OSM the map renders on a fresh clone with no setup and no account.
///
/// Only the tiles changed. Locating the device is still `geolocator` and the
/// address lookup is still the platform geocoder, neither of which is a
/// Google Cloud service or costs anything.
class PharmacyLocationPickerPage extends StatefulWidget {
  final PharmacyLocationDraft? initialLocation;
  final CurrentLocationService? locationService;
  final AddressLookupService? addressLookup;

  const PharmacyLocationPickerPage({
    super.key,
    this.initialLocation,
    this.locationService,
    this.addressLookup,
  });

  @override
  State<PharmacyLocationPickerPage> createState() =>
      _PharmacyLocationPickerPageState();
}

class _PharmacyLocationPickerPageState
    extends State<PharmacyLocationPickerPage> {
  /// Where the camera starts when the pharmacy has no pin yet.
  ///
  /// Centred on Syria rather than on the globe: at world zoom the whole
  /// country is a few pixels wide, so every owner had to pan and zoom across
  /// an ocean before they could place anything. No marker is dropped here —
  /// this only decides what the map is looking at.
  static const _syria = LatLng(34.8, 38.0);
  static const _countryZoom = 6.0;
  static const _streetZoom = 16.0;

  /// OpenStreetMap asks that clients identify themselves in the User-Agent.
  static const _userAgent = 'com.example.phamacy_managment';

  late final PharmacyLocationController _controller;
  final MapController _mapController = MapController();

  @override
  void initState() {
    super.initState();
    _controller = PharmacyLocationController(
      locationService:
          widget.locationService ?? GeolocatorCurrentLocationService(),
      addressLookup: widget.addressLookup ?? GeocodingAddressLookupService(),
      initial: widget.initialLocation,
    )..addListener(_refresh);
  }

  @override
  void dispose() {
    _controller
      ..removeListener(_refresh)
      ..dispose();
    _mapController.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final initial = widget.initialLocation;
    final draft = _controller.draft;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Select Location'),
        leading: IconButton(
          key: const ValueKey('map-cancel-button'),
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.close),
        ),
      ),
      body: Stack(
        children: [
          FlutterMap(
            key: const ValueKey('pharmacy-map'),
            mapController: _mapController,
            options: MapOptions(
              initialCenter: initial == null
                  ? _syria
                  : LatLng(initial.latitude, initial.longitude),
              initialZoom: initial == null ? _countryZoom : _streetZoom,
              // Tapping anywhere moves the pin, which replaces dragging it.
              onTap: (_, point) =>
                  _controller.select(point.latitude, point.longitude),
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: _userAgent,
              ),
              if (draft != null)
                MarkerLayer(
                  markers: [
                    Marker(
                      key: const ValueKey('pharmacy-location-marker'),
                      point: LatLng(draft.latitude, draft.longitude),
                      width: 40,
                      height: 40,
                      // The pin's point, not its middle, is the location.
                      alignment: Alignment.topCenter,
                      child: const Icon(
                        Icons.location_on,
                        size: 40,
                        color: Colors.red,
                      ),
                    ),
                  ],
                ),
              // Attribution is a condition of using OpenStreetMap's tiles.
              const RichAttributionWidget(
                attributions: [
                  TextSourceAttribution('OpenStreetMap contributors'),
                ],
              ),
            ],
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 20,
            child: SafeArea(
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (draft != null)
                        Text(
                          '${draft.latitude.toStringAsFixed(6)}, ${draft.longitude.toStringAsFixed(6)}',
                          key: const ValueKey('map-selected-coordinates'),
                        )
                      else
                        const Text('Tap the map to place a marker.'),
                      if (_controller.failure != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          _failureMessage(_controller.failure!),
                          key: const ValueKey('location-failure-message'),
                          style: const TextStyle(color: Colors.red),
                        ),
                        if (_controller.failure ==
                            LocationFailure.permissionDeniedForever)
                          TextButton(
                            onPressed:
                                _controller.locationService.openAppSettings,
                            child: const Text('Open Settings'),
                          ),
                        if (_controller.failure ==
                            LocationFailure.servicesDisabled)
                          TextButton(
                            onPressed: _controller
                                .locationService
                                .openLocationSettings,
                            child: const Text('Location Settings'),
                          ),
                      ],
                      const SizedBox(height: 10),
                      // Stacked rather than side by side: half a phone width
                      // wraps "Use My Current Location" onto three lines.
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          key: const ValueKey('use-current-location-button'),
                          onPressed: _controller.locating
                              ? null
                              : _useCurrentLocation,
                          icon: _controller.locating
                              ? const SizedBox.square(
                                  dimension: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.my_location),
                          label: const Text('Use My Current Location'),
                        ),
                      ),
                      const SizedBox(height: 8),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          key: const ValueKey('confirm-location-button'),
                          onPressed: _controller.canConfirm
                              ? _confirmLocation
                              : null,
                          child: _controller.confirming
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Confirm Location'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _useCurrentLocation() async {
    final result = await _controller.useCurrentLocation();
    if (result != null) {
      _mapController.move(
        LatLng(result.latitude, result.longitude),
        _streetZoom,
      );
    }
  }

  Future<void> _confirmLocation() async {
    final result = await _controller.confirm();
    if (result != null && mounted) {
      Navigator.pop(context, result);
    }
  }

  String _failureMessage(LocationFailure failure) {
    return switch (failure) {
      LocationFailure.servicesDisabled =>
        'Location services are disabled. You can still select a point manually.',
      LocationFailure.permissionDenied =>
        'Location permission was denied. You can still select a point manually.',
      LocationFailure.permissionDeniedForever =>
        'Location permission is permanently denied. Select manually or open Settings.',
      LocationFailure.timeout =>
        'Current location timed out. Try again or select a point manually.',
      LocationFailure.unavailable =>
        'Current location is unavailable. Select a point manually.',
    };
  }
}
