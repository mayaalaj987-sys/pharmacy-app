<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AcceptsPharmacyCoordinates;

class AddPharmacyRequest extends ApiFormRequest
{
    use AcceptsPharmacyCoordinates;

    public function rules(): array
    {
        return [
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'pharmacy_address' => ['required', 'string', 'max:1000'],
            'certificate' => ['required', 'file', 'max:5120'],
            'license' => ['required', 'file', 'max:5120'],
            'pharmacist_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'status' => ['prohibited'],
            ...$this->coordinateRules(),
        ];
    }
}
