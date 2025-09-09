<?php

namespace App\Http\Requests\Rent;

use App\Http\Requests\BaseFormRequest;

class GenerateInvoiceRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'rental_period_months' => 'required|integer|min:1|max:24',
            'start_date' => 'required|date|after_or_equal:today',
            'lease_terms' => 'nullable|string|max:1000'
        ];
    }


    public function messages(): array
    {
        return [
            'rental_period_months.min' => 'Rental period must be at least 1 month',
            'rental_period_months.max' => 'Rental period cannot exceed 24 months',
            'start_date.after_or_equal' => 'Start date must be today or in the future'
        ];
    }
}
