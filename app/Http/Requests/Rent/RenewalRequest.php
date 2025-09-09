<?php

namespace App\Http\Requests\Rent;

use App\Http\Requests\BaseFormRequest;

class RenewalRequest extends BaseFormRequest
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
            'renewal_period_months' => 'required|integer|min:1|max:24',
            'proposed_start_date' => 'required|date|after_or_equal:today',
            'renewal_notes' => 'nullable|string|max:500',
            'proposed_rent_amount' => 'nullable|numeric|min:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'renewal_period_months.min' => 'Renewal period must be at least 1 month',
            'renewal_period_months.max' => 'Renewal period cannot exceed 24 months',
            'proposed_start_date.after_or_equal' => 'Start date must be today or in the future',
            'proposed_rent_amount.min' => 'Proposed rent must be at least ₦1,000'
        ];
    }
}
