<?php

namespace App\Http\Requests\Property;

use App\Http\Requests\BaseFormRequest;

class CreatePropertyRequest extends BaseFormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'property_type' => 'required|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'required|numeric|min:1',
            'location_address' => 'required|string',
            'state' => 'required|string',
            'lga' => 'required|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'videos' => 'nullable|array|max:3',
            'videos.*' => 'file|mimes:mp4,mov,avi|max:10240' // 10MB max
        ];
    }
}
