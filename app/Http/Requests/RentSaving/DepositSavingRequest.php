<?php

namespace App\Http\Requests\RentSaving;

use App\Http\Requests\BaseFormRequest;

class DepositSavingRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'savings_id' => 'required|exists:rent_savings,id',
            'amount' => 'required|numeric|min:100|max:10000000' // Max 10M per transaction
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum deposit amount is ₦100',
            'amount.max' => 'Maximum deposit amount is ₦10,000,000 per transaction'
        ];
    }
}
