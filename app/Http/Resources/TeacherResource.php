<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray($request)
    {
        $totalStudents = $this->approved_students_count
            ?? $this->students_count
            ?? ($this->taughtGroups ? $this->taughtGroups->sum('students_count') : 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'center_id' => $this->center_id,
            'center' => $this->whenLoaded('center', function () {
                return $this->center ? [
                    'id' => $this->center->id,
                    'name' => $this->center->name,
                ] : null;
            }),
            'taught_groups_count' => $this->taught_groups_count ?? $this->whenCounted('taughtGroups'),
            'approved_students_count' => (int) ($this->approved_students_count ?? 0),
            'pending_students_count' => (int) ($this->pending_students_count ?? 0),
            'total_students' => (int) $totalStudents,
            'created_at' => $this->created_at?->toDateTimeString(),
            'groups' => GroupResource::collection($this->whenLoaded('taughtGroups')),
        ];
    }
}
