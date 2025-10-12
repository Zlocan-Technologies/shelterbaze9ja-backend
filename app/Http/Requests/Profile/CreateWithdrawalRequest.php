<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'trx_pin' => 'sometimes|numeric|digits:4'
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a numeric value.',
            'amount.min' => 'Amount must be at least 0.',
            'bank_name.required' => 'Bank name is required.',
            'bank_name.string' => 'Bank name must be a valid string.',
            'bank_name.max' => 'Bank name must not exceed 255 characters.',
            'account_name.required' => 'Account name is required.',
            'account_name.string' => 'Account name must be a valid string.',
            'account_name.max' => 'Account name must not exceed 255 characters.',
            'account_number.required' => 'Account number is required.',
            'account_number.string' => 'Account number must be a valid string.',
            'account_number.max' => 'Account number must not exceed 255 characters.',
            'trx_pin.numeric' => 'Transaction PIN must be a numeric value.',
            'trx_pin.digits' => 'Transaction PIN must be exactly 4 digits.',
        ];
    }
}
