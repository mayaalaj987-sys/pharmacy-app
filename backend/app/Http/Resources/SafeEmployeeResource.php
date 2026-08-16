<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SafeEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pharmacy_id' => $this->pharmacy_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'salary' => $this->salary === null ? null : (float) $this->salary,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
