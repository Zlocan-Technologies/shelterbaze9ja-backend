<?php

namespace App\Http\Requests\RentSaving;

use App\Http\Requests\BaseFormRequest;

class CreateRentSavingRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:1000|max:50000000', // Max 50M
            'due_date' => 'required|date|after:today|before:' . now()->addYears(5)->format('Y-m-d'),
            'property_id' => 'nullable|exists:properties,id',
            'is_external_property' => 'required|boolean',
            'external_property_details' => 'required_if:is_external_property,true|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'target_amount.min' => 'Minimum target amount is ₦1,000',
            'target_amount.max' => 'Maximum target amount is ₦50,000,000',
            'due_date.after' => 'Due date must be in the future',
            'due_date.before' => 'Due date cannot be more than 5 years from now',
            'external_property_details.required_if' => 'Property details are required for external properties'
        ];
    }
}
