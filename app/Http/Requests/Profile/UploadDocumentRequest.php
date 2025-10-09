<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'document_type' => 'required|string|in:id_card,utility_bill,bank_statement',
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Please upload a document.',
            'document.file' => 'The uploaded document must be a file.',
            'document.mimes' => 'The document must be a file of type: pdf, jpg, jpeg, png.',
            'document.max' => 'The document may not be greater than 5MB.',
            'document_type.required' => 'Please specify the type of document.',
            'document_type.string' => 'The document type must be a string.',
            'document_type.in' => 'The document type must be one of the following: id_card, utility_bill, bank_statement.',
        ];
    }
}
