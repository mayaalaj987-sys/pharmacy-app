<?php

namespace App\Http\Requests;

use App\Models\Admin;
use Illuminate\Validation\Rules\Password;

class CreateAdminRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', 'in:'.implode(',', Admin::ROLES)],
        ];
    }
}
