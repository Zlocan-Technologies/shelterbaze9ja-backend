<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class CreateProfileRequest extends BaseFormRequest
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|unique:users|regex:/^\+234[0-9]{10}$/',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,landlord,agent',
            'otp_code' => 'required|digits:6',
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'The phone number must be a valid Nigerian number starting with +234 followed by 10 digits.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'Email has already been taken.',
            'phone_number.required' => 'Phone number is required.',
            'phone_number.unique' => 'Phone number has already been taken.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of the following: user, landlord, or agent.',
            'otp_code.required' => 'OTP is required',
            'otp_code.digits' => 'OTP should be 6 digits'
        ];
    }
}
