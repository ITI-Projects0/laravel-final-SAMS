<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
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
            'title' => $this->title,
            'max_score' => $this->max_score,
            'scheduled_at' => $this->scheduled_at,
            'group_id' => $this->group_id,
            'center_id' => $this->center_id,
            'lesson_id' => $this->lesson_id,
            'group' => new GroupResource($this->whenLoaded('group')),
            'lesson' => new LessonResource($this->whenLoaded('lesson')),
            'results' => $this->whenLoaded('results'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
