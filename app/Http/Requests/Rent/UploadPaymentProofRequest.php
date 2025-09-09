<?php

namespace App\Http\Requests\Rent;

use App\Http\Requests\BaseFormRequest;

class UploadPaymentProofRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rental_agreement_id' => 'required_if:payment_type,online|exists:rental_agreements,id',
            'payment_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_type' => 'required|in:online,offline',
            'amount' => 'required|numeric|min:1000',
            'payment_reference' => 'nullable|string|max:100',
            'additional_notes' => 'nullable|string|max:500',

            //only required if payment_type is offline
            'property_id' => 'required_if:payment_type,offline|exists:properties,id',
            'rental_period_months' => 'required_if:payment_type,offline|integer|min:1|max:24',
            'start_date' => 'required_if:payment_type,offline|date|after_or_equal:today',
            'lease_terms' => 'nullable|string|max:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.max' => 'Payment proof file cannot exceed 5MB',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'amount.min' => 'Payment amount must be at least ₦1,000',
            'rental_period_months.min' => 'Rental period must be at least 1 month',
            'rental_period_months.max' => 'Rental period cannot exceed 24 months',
            'start_date.after_or_equal' => 'Start date must be today or in the future'
        ];
    }
}
