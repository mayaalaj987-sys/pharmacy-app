<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class UpdatePharmacyProfileRequest extends ApiFormRequest
{
    public function validationData(): array
    {
        $data = parent::validationData();

        if (! $this->attributes->get('client_supplied_pharmacy_id', false)) {
            unset($data['pharmacy_id']);
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'pharmacy_name' => ['sometimes', 'required', 'string', 'max:255'],
            'pharmacy_address' => ['sometimes', 'required', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'id' => ['prohibited'],
            'pharmacy_id' => ['prohibited'],
            'pharmacist_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'status' => ['prohibited'],
            'approval_status' => ['prohibited'],
            'approved' => ['prohibited'],
            'certificate' => ['prohibited'],
            'license' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasLatitude = $this->exists('latitude');
                $hasLongitude = $this->exists('longitude');

                if ($hasLatitude !== $hasLongitude) {
                    $missing = $hasLatitude ? 'longitude' : 'latitude';
                    $validator->errors()->add($missing, 'Latitude and longitude must be provided together.');
                }

                if ($hasLatitude && $hasLongitude && (($this->input('latitude') === null) !== ($this->input('longitude') === null))) {
                    $validator->errors()->add('coordinates', 'Latitude and longitude must both be numeric or both be null.');
                }
            },
        ];
    }
}
