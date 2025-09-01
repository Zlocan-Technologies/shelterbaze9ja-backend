<?php

namespace App\Http\Requests\RentSaving;

use App\Http\Requests\BaseFormRequest;

class UpdateRentSavingRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_name' => 'sometimes|string|max:255',
            'target_amount' => 'sometimes|numeric|min:1000|max:50000000',
            'due_date' => 'sometimes|date|after:today|before:' . now()->addYears(5)->format('Y-m-d'),
            'external_property_details' => 'sometimes|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'target_amount.min' => 'Minimum target amount is ₦1,000',
            'target_amount.max' => 'Maximum target amount is ₦50,000,000',
            'due_date.after' => 'Due date must be in the future',
            'due_date.before' => 'Due date cannot be more than 5 years from now'
        ];
    }
}
