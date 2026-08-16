<?php

namespace App\Http\Requests;

class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'profile_image' => ['sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'current_password' => ['prohibited'],
            'new_password' => ['prohibited'],
            'new_password_confirmation' => ['prohibited'],
            'id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'account_id' => ['prohibited'],
            'pharmacist_id' => ['prohibited'],
            'status' => ['prohibited'],
            'deactivated_at' => ['prohibited'],
            'token' => ['prohibited'],
            'abilities' => ['prohibited'],
        ];
    }
}
