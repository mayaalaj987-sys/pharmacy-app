<?php

namespace App\Http\Requests;

class RegisterEmployeeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            'password' => ['required', 'string', 'min:6'],
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
