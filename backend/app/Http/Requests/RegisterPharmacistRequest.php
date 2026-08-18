<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AcceptsPharmacyCoordinates;
use Illuminate\Validation\Rules\Password;

class RegisterPharmacistRequest extends ApiFormRequest
{
    use AcceptsPharmacyCoordinates;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:pharmacists,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            // Matches the change-password rule. Registration was the weaker of
            // the two, which put the floor at the moment the account is born.
            'password' => ['required', 'string', Password::min(8)],
            'profile_image' => ['nullable', 'file', 'max:2048'],
            'profile' => ['prohibited'],
            'pharmacist_id' => ['prohibited'],
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'pharmacy_address' => ['required', 'string', 'max:1000'],
            'certificate' => ['required', 'file', 'max:5120'],
            'license' => ['required', 'file', 'max:5120'],
            'status' => ['prohibited'],
            ...$this->coordinateRules(),
        ];
    }
}
