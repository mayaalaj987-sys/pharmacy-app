<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class RegisterEmployeeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            // Matches the change-password rule. Registration was the weaker of
            // the two, which put the floor at the moment the account is born.
            'password' => ['required', 'string', Password::min(8)],
            'cv' => ['required', 'file', 'max:5120'],
            'experience_proof' => ['required_if:role,employee', 'nullable', 'file', 'max:5120'],
            'role' => ['required', 'in:employee,trainee'],
            'pharmacy_id' => ['prohibited'],
            'status' => ['prohibited'],
            'salary' => ['prohibited'],
            'first_login' => ['prohibited'],
        ];
    }
}
