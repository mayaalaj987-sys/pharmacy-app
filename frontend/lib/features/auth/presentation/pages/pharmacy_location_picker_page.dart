import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../account/data/location_services.dart';
import '../../../account/domain/pharmacy_location_draft.dart';
import '../../../account/presentation/controllers/pharmacy_location_controller.dart';

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
  late final PharmacyLocationController _controller;
  GoogleMapController? _mapController;

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
    _mapController?.dispose();
    super.dispose();
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final initial = widget.initialLocation;
    final initialTarget = initial == null
        ? const LatLng(20, 0)
        : LatLng(initial.latitude, initial.longitude);
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
          GoogleMap(
            key: const ValueKey('pharmacy-google-map'),
            initialCameraPosition: CameraPosition(
              target: initialTarget,
              zoom: initial == null ? 2 : 16,
            ),
            onMapCreated: (controller) => _mapController = controller,
            onTap: (point) =>
                _controller.select(point.latitude, point.longitude),
            markers: draft == null
                ? const <Marker>{}
                : {
                    Marker(
                      markerId: const MarkerId('pharmacy-location'),
                      position: LatLng(draft.latitude, draft.longitude),
                      draggable: true,
                      onDragEnd: (point) => _controller.markerDragged(
                        point.latitude,
                        point.longitude,
                      ),
                    ),
                  },
            myLocationEnabled: false,
            myLocationButtonEnabled: false,
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
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              key: const ValueKey(
                                'use-current-location-button',
                              ),
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
                          const SizedBox(width: 10),
                          Expanded(
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
      await _mapController?.animateCamera(
        CameraUpdate.newLatLngZoom(
          LatLng(result.latitude, result.longitude),
          16,
        ),
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
