<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;
use App\Models\ChatConversation;

class UpdateConversationRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:' . ChatConversation::STATUS_ACTIVE . ',' . ChatConversation::STATUS_CLOSED,
            'notes' => 'sometimes|string|max:500'
        ];
    }
}
