<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeDocumentVersion;

class EmployeeDocumentVersionPolicy
{
    public function view(mixed $user, EmployeeDocumentVersion $document): bool
    {
        return $user instanceof Employee && (int) $document->employee_id === (int) $user->id;
    }
}
