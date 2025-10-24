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
            'media' => 'required|array|min:1',
            'media.*.media_type' => 'required|in:image,video',
            'media.*.file' => 'required|file',
            'media.*.is_primary' => 'sometimes|boolean'
        ];
    }

    public function validationRules(): array
    {
        $rules = $this->rules();
        
        // Add dynamic validation based on media type for each item
        $mediaItems = $this->input('media', []);
        
        foreach ($mediaItems as $index => $mediaItem) {
            if (isset($mediaItem['media_type'])) {
                if ($mediaItem['media_type'] === 'image') {
                    $rules["media.{$index}.file"] = 'required|image|mimes:jpeg,png,jpg|max:2048'; // 2MB max
                } elseif ($mediaItem['media_type'] === 'video') {
                    $rules["media.{$index}.file"] = 'required|file|mimes:mp4,mov,avi|max:10240'; // 10MB max
                }
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'media.required' => 'At least one media item is required.',
            'media.array' => 'Media must be an array of media items.',
            'media.min' => 'At least one media item must be uploaded.',
            'media.*.media_type.required' => 'Media type is required for each item.',
            'media.*.media_type.in' => 'Media type must be either "image" or "video".',
            'media.*.file.required' => 'File is required for each media item.',
            'media.*.file.file' => 'Each media item must be a valid file.',
            'media.*.file.image' => 'Image files must be valid image files.',
            'media.*.file.mimes' => 'Invalid file format. Images: jpeg,png,jpg. Videos: mp4,mov,avi.',
            'media.*.file.max' => 'File size exceeds the maximum limit (2MB for images, 10MB for videos).',
            'media.*.is_primary.boolean' => 'Primary indicator must be true or false.'
        ];
    }
}
