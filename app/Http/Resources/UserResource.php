<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'center_id' => $this->center_id,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'role' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')->first()),
            'center' => new CenterResource($this->whenLoaded('center')),
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
            'children' => UserResource::collection($this->whenLoaded('children')),
            'groups_count' => $this->whenCounted('groups'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
