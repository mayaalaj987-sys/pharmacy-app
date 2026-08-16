<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'password_changed_at' => $this->password_changed_at?->toISOString(),
            'disabled_at' => $this->disabled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
