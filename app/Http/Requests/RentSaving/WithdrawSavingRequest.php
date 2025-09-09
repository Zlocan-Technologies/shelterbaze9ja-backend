<?php

namespace App\Http\Requests\RentSaving;

use App\Http\Requests\BaseFormRequest;

class WithdrawSavingRequest extends BaseFormRequest
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
            'amount' => 'required|numeric|min:100',
            'withdrawal_reason' => 'sometimes|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum withdrawal amount is ₦100'
        ];
    }
}
