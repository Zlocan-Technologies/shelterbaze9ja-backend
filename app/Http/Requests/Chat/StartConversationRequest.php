<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class StartConversationRequest extends BaseFormRequest
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
            'message' => 'required|string|max:1000'
        ];
    }
}
