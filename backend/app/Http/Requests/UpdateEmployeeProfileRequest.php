<?php

namespace App\Http\Requests;

class UpdateEmployeeProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            // Tenant, role and lifecycle fields are never self-assignable.
            'pharmacy_id' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'salary' => ['prohibited'],
            'first_login' => ['prohibited'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'cv' => ['prohibited'],
            'experience_proof' => ['prohibited'],
            'id' => ['prohibited'],
            'employee_id' => ['prohibited'],
        ];
    }
}
