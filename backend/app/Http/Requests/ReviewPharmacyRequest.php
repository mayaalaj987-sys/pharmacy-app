<?php

namespace App\Http\Requests;

class ReviewPharmacyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['review_version' => ['required', 'integer', 'min:0']];
    }
}
