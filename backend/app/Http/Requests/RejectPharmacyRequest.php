<?php

namespace App\Http\Requests;

class RejectPharmacyRequest extends ReviewPharmacyRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
    }
}
