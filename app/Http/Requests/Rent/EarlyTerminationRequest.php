<?php

namespace App\Http\Requests\Rent;

use App\Http\Requests\BaseFormRequest;

class EarlyTerminationRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'termination_date' => 'required|date|after:today',
            'termination_reason' => 'required|string|max:1000',
            'willing_to_pay_penalty' => 'required|boolean'
        ];
    }
}
