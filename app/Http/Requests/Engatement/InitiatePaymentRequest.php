<?php

namespace App\Http\Requests\Engatement;

use App\Http\Requests\BaseFormRequest;

class InitiatePaymentRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id'
        ];
    }
}
