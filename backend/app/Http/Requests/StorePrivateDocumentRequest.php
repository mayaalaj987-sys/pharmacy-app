<?php

namespace App\Http\Requests;

class StorePrivateDocumentRequest extends ApiFormRequest
{
    public function validationData(): array
    {
        $data = parent::validationData();
        if ($this->attributes->has('client_supplied_pharmacy_id')
            && ! $this->attributes->get('client_supplied_pharmacy_id')) {
            unset($data['pharmacy_id']);
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:5120'],
            'file' => ['prohibited'],
            'path' => ['prohibited'],
            'storage_key' => ['prohibited'],
            'disk' => ['prohibited'],
            'review_status' => ['prohibited'],
            'pharmacy_id' => ['prohibited'],
            'employee_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
        ];
    }
}
