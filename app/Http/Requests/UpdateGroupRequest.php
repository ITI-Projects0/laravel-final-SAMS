<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
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
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'subject' => 'sometimes|required|string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.max' => 'Group name may not exceed 255 characters.',

            'subject.required' => 'Subject is required.',
            'subject.max' => 'Subject name may not exceed 100 characters.',

            'description.max' => 'Description may not exceed 2000 characters.',

            'center_id.required' => 'Center is required.',
            'center_id.exists' => 'The selected center does not exist.',

            'teacher_id.required' => 'Teacher is required.',
            'teacher_id.exists' => 'The selected teacher does not exist.',

            'schedule_days.array' => 'Schedule days must be a list.',
            'schedule_days.min' => 'At least one schedule day must be selected.',
            'schedule_days.*.in' => 'Invalid day selected in the schedule.',

            'schedule_time.date_format' => 'Invalid time format (example: 16:00).',

            'sessions_count.integer' => 'Sessions count must be an integer.',
            'sessions_count.min' => 'Sessions count must be at least 1.',
            'sessions_count.max' => 'Sessions count may not exceed 100.',

            'is_active.boolean' => 'Invalid active status.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Group Name',
            'subject' => 'Subject',
            'description' => 'Description',
            'center_id' => 'Center',
            'teacher_id' => 'Teacher',
            'schedule_days' => 'Schedule Days',
            'schedule_time' => 'Session Time',
            'sessions_count' => 'Sessions Count',
            'is_active' => 'Active Status',
        ];
    }

}
