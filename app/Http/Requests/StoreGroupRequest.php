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
            'description' => 'nullable|string',
            'subject' => 'required|string|max:100',
            'center_id' => 'required|exists:centers,id',
            'teacher_id' => 'required|exists:users,id',
            'academic_year' => 'nullable|string|max:50',
            'schedule_days' => 'nullable|array',
            'schedule_time' => 'nullable|date_format:H:i',
            'sessions_count' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }
}
