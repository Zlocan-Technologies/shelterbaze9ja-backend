<?php

namespace App\Http\Requests\Property;

use App\Http\Requests\BaseFormRequest;

class UploadMediaRequest extends BaseFormRequest
{
   
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media' => 'required|file',
            'media_type' => 'required|in:image,video',
            'is_primary' => 'sometimes|boolean'
        ];
    }
}
