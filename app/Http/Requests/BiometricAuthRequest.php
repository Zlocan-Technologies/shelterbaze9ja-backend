<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometricAuthRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint for authentication
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'biometric_data' => 'required|array',
            'biometric_data.template' => 'required|string|min:10',
            'biometric_data.type' => 'required|string|in:fingerprint,face,voice',
            'device_id' => 'required|string|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account found with this email address.',
            'biometric_data.required' => 'Biometric data is required for authentication.',
            'biometric_data.template.required' => 'Biometric template is required.',
            'biometric_data.template.min' => 'Invalid biometric template format.',
            'biometric_data.type.required' => 'Biometric type is required.',
            'biometric_data.type.in' => 'Biometric type must be one of: fingerprint, face, voice.',
            'device_id.required' => 'Device ID is required for biometric authentication.'
        ];
    }
}