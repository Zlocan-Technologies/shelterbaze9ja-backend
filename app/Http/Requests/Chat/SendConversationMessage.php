<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class SendConversationMessage extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chat_conversation_id' => 'required|exists:chat_conversations,id',
            'message' => 'required|string|min:1'
        ];
    }
}
