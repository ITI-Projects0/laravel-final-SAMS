<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized via middleware/controller checks
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'max_score' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Assessment title is required.',
            'title.max' => 'Assessment title cannot exceed 255 characters.',
            'scheduled_at.required' => 'Scheduled date is required.',
            'scheduled_at.date' => 'Invalid date format.',
            'max_score.integer' => 'Max score must be an integer.',
            'max_score.min' => 'Max score cannot be negative.',
        ];
    }
}
