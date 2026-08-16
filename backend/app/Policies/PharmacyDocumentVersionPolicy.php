<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Pharmacist;
use App\Models\PharmacyDocumentVersion;

class PharmacyDocumentVersionPolicy
{
    public function view(mixed $user, PharmacyDocumentVersion $document): bool
    {
        return $user instanceof Pharmacist
            && (int) $document->pharmacy?->pharmacist_id === (int) $user->id;
    }

    public function review(Admin $admin, PharmacyDocumentVersion $document): bool
    {
        return $admin->canReviewPharmacies()
            && $document->pharmacy !== null
            && $document->pharmacy->status === 'pending';
    }
}
