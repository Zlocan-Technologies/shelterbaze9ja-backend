<?php

namespace App\Http\Requests\Agent;

use App\Http\Requests\BaseFormRequest;

class VerifyPropertyRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'verification_images' => 'required|array|min:3|max:10',
            'verification_images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'verification_notes' => 'required|string|max:1000',
            'longitude' => 'required|numeric|between:-180,180',
            'latitude' => 'required|numeric|between:-90,90',
            'status' => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
            'property_condition' => 'sometimes|in:excellent,good,fair,poor',
            'accessibility_notes' => 'nullable|string|max:300',
            'surrounding_area_notes' => 'nullable|string|max:300'
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.required' => 'Property ID is required.',
            'property_id.exists' => 'The specified property does not exist.',
            'verification_images.required' => 'At least 3 verification images are required.',
            'verification_images.array' => 'Verification images must be an array.',
            'verification_images.min' => 'At least 3 verification images are required.',
            'verification_images.max' => 'A maximum of 10 verification images are allowed.',
            'verification_images.*.image' => 'Each verification image must be a valid image file.',
            'verification_images.*.mimes' => 'Verification images must be in jpeg, png, or jpg format.',
            'verification_images.*.max' => 'Each verification image must not exceed 5MB in size.',
            'verification_notes.required' => 'Verification notes are required.',
            'verification_notes.string' => 'Verification notes must be a valid string.',
            'verification_notes.max' => 'Verification notes must not exceed 1000 characters.',
            'longitude.required' => 'Longitude is required.',
            'longitude.numeric' => 'Longitude must be a numeric value.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
            'latitude.required' => 'Latitude is required.',
            'latitude.numeric' => 'Latitude must be a numeric value.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'status.required' => 'Verification status is required.',
            'status.in' => 'Status must be either verified or rejected.',
            'rejection_reason.required_if' => 'Rejection reason is required when status is rejected.',
            'rejection_reason.string' => 'Rejection reason must be a valid string.',
            'rejection_reason.max' => 'Rejection reason must not exceed 500 characters.',
            'property_condition.in' => 'Property condition must be one of: excellent, good, fair, poor.',
            'accessibility_notes.string' => 'Accessibility notes must be a valid string.',
            'accessibility_notes.max' => 'Accessibility notes must not exceed 300 characters.',
            'surrounding_area_notes.string' => 'Surrounding area notes must be a valid string.',
            'surrounding_area_notes.max' => 'Surrounding area notes must not exceed 300 characters.'
        ];
    }
}
