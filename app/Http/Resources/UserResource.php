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
            'role' => $this->roles->first()->name ?? $this->getRoleNames()->first(),
            'center' => $this->whenLoaded('center', function () {
                return $this->center ? new CenterResource($this->center) : null;
            }),
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
            'groups_count' => $this->whenCounted('groups'),
            'parents' => $this->whenLoaded('parents', function () {
                return $this->parents->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'relationship' => $p->pivot?->relationship ?? 'parent',
                ]);
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
