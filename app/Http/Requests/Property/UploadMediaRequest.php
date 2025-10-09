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
            'media_type' => 'required|in:image,video',
            'media' => 'required|file',
            'is_primary' => 'sometimes|boolean'
        ];
    }

    public function validationRules(): array
    {
        $rules = $this->rules();

        if ($this->input('media_type') === 'image') {
            $rules['media'] = 'required|image|mimes:jpeg,png,jpg|max:2048'; // 2MB max
        } elseif ($this->input('media_type') === 'video') {
            $rules['media'] = 'required|file|mimes:mp4,mov,avi|max:10240'; // 10MB max
        }

        return $rules;
    }
}
