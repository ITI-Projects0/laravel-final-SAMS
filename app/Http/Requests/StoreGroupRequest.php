<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'subject' => 'required|string|max:100',
            'center_id' => 'required|exists:centers,id',
            'teacher_id' => 'sometimes|exists:users,id',
            'academic_year' => 'nullable|string|max:50',
            'schedule_days' => 'required|array|min:1',
            'schedule_days.*' => 'string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'schedule_time' => 'nullable|date_format:H:i',
            'sessions_count' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
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
            'name.max' => 'Group name cannot exceed 255 characters.',
            'subject.required' => 'Subject is required.',
            'subject.max' => 'Subject cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'center_id.required' => 'Center is required.',
            'center_id.exists' => 'Selected center does not exist.',
            'teacher_id.exists' => 'Selected teacher does not exist.',
            'schedule_days.required' => 'Schedule days are required.',
            'schedule_days.array' => 'Schedule days must be a list.',
            'schedule_days.min' => 'At least one schedule day is required.',
            'schedule_days.*.in' => 'Invalid day in schedule. Valid days: Saturday, Sunday, Monday, Tuesday, Wednesday, Thursday, Friday.',
            'schedule_time.date_format' => 'Invalid time format. Use HH:MM format (e.g., 16:00).',
            'sessions_count.integer' => 'Sessions count must be an integer.',
            'sessions_count.min' => 'Sessions count must be at least 1.',
            'sessions_count.max' => 'Sessions count cannot exceed 100.',
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
            'name' => 'group name',
            'subject' => 'subject',
            'description' => 'description',
            'center_id' => 'center',
            'teacher_id' => 'teacher',
            'schedule_days' => 'schedule days',
            'schedule_time' => 'schedule time',
            'sessions_count' => 'sessions count',
            'is_active' => 'active status',
        ];
    }
}
