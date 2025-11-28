<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'subject'               => $this->subject,
            'description'           => $this->description,
            'join_code'             => $this->join_code,
            'is_approval_required'  => $this->is_approval_required,
            'is_active'             => $this->is_active,
            'center'                => $this->center?->name,
            'teacher'               => $this->whenLoaded('teacher', fn() => [
                'id'   => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'students_count'        => $this->whenLoaded('students', fn() => $this->students->count()),
            'pending_requests'      => $this->whenLoaded('pendingStudents', fn() => $this->pendingStudents->count()),
            'created_at'            => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
