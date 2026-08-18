# Maps setup

There is nothing to set up. Clone the repository, `flutter run`, and the map
renders.

## Why there is no API key

The pharmacy location picker draws OpenStreetMap tiles through `flutter_map`.
OSM needs no credential, so the repository holds no map secret and a new
contributor needs no account.

Google Maps was used first and was replaced. Its Android SDK requires an API
key, the key requires a Google Cloud project, and the project requires a
billing account with a payment method attached — even though mobile map loads
themselves are not charged. Google Cloud is also unavailable in some of the
countries this app is meant to run in, which made the map unrenderable for the
people it was built for. A missing key does not fail loudly either: the SDK
draws an empty grey canvas that still accepts taps, so the map looks broken
rather than unconfigured.

## What is still native, and still free

Only the tiles changed.

- Locating the device is `geolocator`, which calls Android's own location
  services.
- Turning a point into a street name is `geocoding`, which calls Android's
  built-in `Geocoder` — not Google's billable Geocoding API.

Neither is a Google Cloud service and neither needs a key.

## Tile usage

`TileLayer` sends `userAgentPackageName` because OpenStreetMap's tile policy
asks clients to identify themselves, and the map carries the attribution OSM
requires. The public tile servers are fine for development and for a
deployment of this size. A production rollout at real volume should move to a
tile provider — Thunderforest, MapTiler, Stadia and others serve the same OSM
data — which is a change to one `urlTemplate`.
