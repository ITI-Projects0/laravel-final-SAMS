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
            'id' => $this->id,
            'name' => $this->name,
            'subject' => $this->subject,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'schedule_days' => $this->schedule_days,
            'schedule_time' => $this->schedule_time?->format('H:i'),
            'sessions_count' => $this->sessions_count,
            'teacher' => new UserResource($this->whenLoaded('teacher')),
            'center' => new CenterResource($this->whenLoaded('center')),
            'students_count' => $this->whenCounted('students'),
            'pending_students_count' => $this->whenCounted('pendingStudents'),
            'lessons_count' => $this->whenCounted('lessons'),
            'attendance_today_count' => $this->when(isset($this->attendance_today_count), (int) $this->attendance_today_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
