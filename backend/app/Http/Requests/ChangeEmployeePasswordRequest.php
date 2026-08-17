<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class ChangeEmployeePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
