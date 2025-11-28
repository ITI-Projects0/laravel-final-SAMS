<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'groups_count' => $this->whenCounted('taughtGroups'),
            'groups' => GroupResource::collection(
                $this->whenLoaded('taughtGroups')
            ),
        ];
    }
}
