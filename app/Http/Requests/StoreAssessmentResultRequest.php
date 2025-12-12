<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assessment = $this->route('assessment');
        $maxScore = $assessment ? $assessment->max_score : 100;

        return [
            'student_id' => 'required|exists:users,id',
            'score' => 'required|numeric|min:0|max:' . $maxScore,
            'feedback' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student ID is required.',
            'student_id.exists' => 'Selected student does not exist.',
            'score.required' => 'Score is required.',
            'score.numeric' => 'Score must be a number.',
            'score.min' => 'Score cannot be negative.',
            'score.max' => 'Score cannot exceed the assessment maximum score.',
        ];
    }
}
