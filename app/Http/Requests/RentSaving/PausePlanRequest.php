<?php

namespace App\Http\Requests\RentSaving;

use App\Http\Requests\BaseFormRequest;

class PausePlanRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pause_reason' => 'required|string|max:500',
            'resume_date' => 'nullable|date|after:today'
        ];
    }
}
