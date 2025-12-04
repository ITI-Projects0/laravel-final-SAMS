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
            // 'join_code' => 'nullable|string|unique:groups,join_code',
            // 'is_approval_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
