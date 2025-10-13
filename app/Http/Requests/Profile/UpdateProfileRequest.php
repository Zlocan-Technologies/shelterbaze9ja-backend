<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $this->user()->id,
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.string' => 'First name must be a valid string.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.string' => 'Last name must be a valid string.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'phone_number.string' => 'Phone number must be a valid string.',
            'phone_number.unique' => 'The phone number has already been taken.',
            'profile_picture.image' => 'Profile picture must be a valid image file.',
            'profile_picture.mimes' => 'Profile picture must be in jpeg, png, jpg, or gif format.',
            'profile_picture.max' => 'Profile picture must not exceed 2MB in size.',
        ];
    }
}
