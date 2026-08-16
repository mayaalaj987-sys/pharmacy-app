<?php

namespace App\Http\Requests;

class RegisterPharmacistRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:pharmacists,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'profile_image' => ['nullable', 'file', 'max:2048'],
            'profile' => ['prohibited'],
            'pharmacist_id' => ['prohibited'],
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'pharmacy_address' => ['required', 'string', 'max:1000'],
            'certificate' => ['required', 'file', 'max:5120'],
            'license' => ['required', 'file', 'max:5120'],
            'status' => ['prohibited'],
        ];
    }
}
