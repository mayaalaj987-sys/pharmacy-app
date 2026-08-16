<?php

namespace App\Http\Requests;

class DeactivateAccountRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }
}
