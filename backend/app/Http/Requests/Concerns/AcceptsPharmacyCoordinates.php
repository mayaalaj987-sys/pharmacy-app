<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Optional map coordinates on the two requests that create a pharmacy.
 *
 * Picking a point on the map is a convenience, not a requirement: it needs a
 * location permission, a network round-trip and Play Services, and none of
 * those can be a precondition for registering. So the address stays the
 * required field and the coordinates ride along when the client has them.
 *
 * A half-pair is always a client bug — a latitude without a longitude is not a
 * place — so it is rejected rather than silently dropped.
 *
 * `UpdatePharmacyProfileRequest` keeps its own copy of this check because
 * editing also has to express "clear the pin", which creation cannot.
 */
trait AcceptsPharmacyCoordinates
{
    protected function coordinateRules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasLatitude = $this->filled('latitude');
                $hasLongitude = $this->filled('longitude');

                if ($hasLatitude !== $hasLongitude) {
                    $validator->errors()->add(
                        $hasLatitude ? 'longitude' : 'latitude',
                        'Latitude and longitude must be provided together.',
                    );
                }
            },
        ];
    }

    /**
     * The coordinates to persist, or nulls when no point was picked.
     *
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function pharmacyCoordinates(): array
    {
        if (! $this->filled('latitude') || ! $this->filled('longitude')) {
            return ['latitude' => null, 'longitude' => null];
        }

        return [
            'latitude' => (float) $this->validated('latitude'),
            'longitude' => (float) $this->validated('longitude'),
        ];
    }
}
