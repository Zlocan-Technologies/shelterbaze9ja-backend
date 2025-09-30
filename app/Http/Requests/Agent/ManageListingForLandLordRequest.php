<?php

namespace App\Http\Requests\Agent;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class ManageListingForLandLordRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'landlord_id' => 'required|exists:users,id',
            'action' => 'required|in:create,update,delete,toggle_status',
            'property_id' => 'required_unless:action,create|exists:properties,id',
            // Property creation/update fields
            'title' => 'required_if:action,create|sometimes|string|max:255',
            'description' => 'required_if:action,create|sometimes|string|max:2000',
            'property_type' => 'required_if:action,create|sometimes|in:1_bedroom,2_bedroom,3_bedroom,4_bedroom,studio,duplex,bungalow',
            'rent_amount' => 'required_if:action,create|sometimes|numeric|min:1000',
            'location_address' => 'required_if:action,create|sometimes|string|max:500',
            'state' => 'required_if:action,create|sometimes|string|max:100',
            'lga' => 'required_if:action,create|sometimes|string|max:100',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'images' => 'required_if:action,create|sometimes|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ];
    }

    public function messages(): array
    {
        return [
            'landlord_id.required' => 'Landlord ID is required.',
            'landlord_id.exists' => 'The specified landlord does not exist.',
            'action.required' => 'Action is required.',
            'action.in' => 'Action must be one of: create, update, delete, toggle_status.',
            'property_id.required_unless' => 'Property ID is required unless action is create.',
            'property_id.exists' => 'The specified property does not exist.',
            'title.required_if' => 'Property title is required when creating a listing.',
            'description.required_if' => 'Property description is required when creating a listing.',
            'property_type.required_if' => 'Property type is required when creating a listing.',
            'property_type.in' => 'Property type must be one of: 1_bedroom, 2_bedroom, 3_bedroom, 4_bedroom, studio, duplex, bungalow.',
            'rent_amount.required_if' => 'Rent amount is required when creating a listing.',
            'rent_amount.numeric' => 'Rent amount must be a valid number.',
            'rent_amount.min' => 'Rent amount must be at least 1000.',
            'location_address.required_if' => 'Location address is required when creating a listing.',
            'state.required_if' => 'State is required when creating a listing.',
            'lga.required_if' => 'LGA is required when creating a listing.',
            'longitude.numeric' => 'Longitude must be a valid number.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'latitude.numeric' => 'Latitude must be a valid number.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'facilities.array' => 'Facilities must be an array of strings.',
            'facilities.*.string' => 'Each facility must be a string.',
            'facilities.*.max' => 'Each facility must not exceed 100 characters.',
            'images.required_if' => 'At least one image is required when creating a listing.',
            'images.array' => 'Images must be an array of files.',
            'images.min' => 'At least one image is required.',
            'images.max' => 'You can upload up to 10 images only.',
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.mimes' => 'Images must be of type: jpeg, png, jpg.',
            'images.*.max' => 'Each image must not exceed 2MB in size.'
        ];      
    }   
}
