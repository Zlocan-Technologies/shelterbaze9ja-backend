<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;

class SearchConversationRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'required|string|min:2'
        ];
    }
}
