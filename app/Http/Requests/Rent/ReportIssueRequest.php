<?php

namespace App\Http\Requests\Rent;

use App\Http\Requests\BaseFormRequest;

class ReportIssueRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'issue_type' => 'required|in:maintenance,noise,security,utilities,plumbing,electrical,structural,cleaning,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'priority' => 'required|in:low,medium,high,urgent',
            'location_in_property' => 'nullable|string|max:100',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,png,jpg,pdf,mp4,mov|max:10240' // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.*.max' => 'Each attachment cannot exceed 10MB',
            'attachments.max' => 'Maximum 5 attachments allowed'
        ];
    }
}
