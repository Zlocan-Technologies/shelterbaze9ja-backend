<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\BaseFormRequest;

class CompleteProfileRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nin_number' => 'required|string|size:11|unique:user_profiles',
            'nin_selfie' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'required|string',
            'state' => 'required|string',
            'lga' => 'required|string',
        ];
    }
}
