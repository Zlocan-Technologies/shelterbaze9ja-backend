<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BiometricEnrollRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'biometric_data' => 'required|array',
            'biometric_data.template' => 'required|string|min:10',
            'biometric_data.type' => 'required|string|in:fingerprint,face,voice',
            'biometric_data.version' => 'nullable|string',
            'device_id' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'biometric_data.required' => 'Biometric data is required for enrollment.',
            'biometric_data.template.required' => 'Biometric template is required.',
            'biometric_data.template.min' => 'Biometric template must be at least 10 characters.',
            'biometric_data.type.required' => 'Biometric type is required.',
            'biometric_data.type.in' => 'Biometric type must be one of: fingerprint, face, voice.',
            'device_id.required' => 'Device ID is required for biometric enrollment.',
            'device_id.max' => 'Device ID must not exceed 255 characters.'
        ];
    }
}