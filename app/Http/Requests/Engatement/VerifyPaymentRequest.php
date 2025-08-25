<?php

namespace App\Http\Requests\Engatement;

use App\Http\Requests\BaseFormRequest;

class VerifyPaymentRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => 'required|string|exists:engagement_fees,payment_reference'
        ];
    }
}
