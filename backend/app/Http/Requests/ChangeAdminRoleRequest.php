<?php

namespace App\Http\Requests;

use App\Models\Admin;

class ChangeAdminRoleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['role' => ['required', 'string', 'in:'.implode(',', Admin::ROLES)]];
    }
}
