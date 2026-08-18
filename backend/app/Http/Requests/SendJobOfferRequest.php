<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Validation\Rule;

class SendJobOfferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shift' => ['required', Rule::in(Employee::SHIFTS)],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['prohibited'],
            // pharmacy_id is deliberately not prohibited: ActivePharmacyContext
            // merges it into the request before this runs. The tenant is taken
            // from the resolved context, never read from here.
        ];
    }
}
