<?php

namespace App\Http\Requests\Property;

use App\Http\Requests\BaseFormRequest;

class UpdatePropertyRequest extends BaseFormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'property_type' => 'sometimes|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'sometimes|numeric|min:1',
            'location_address' => 'sometimes|string',
            'state' => 'sometimes|string',
            'lga' => 'sometimes|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'status' => 'sometimes|in:open,closed'
        ];
    }
}
