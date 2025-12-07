<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['center_admin', 'teacher', 'assistant']);
    }

    public function update(User $user, Group $group): bool
    {
        return $this->canManage($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->canManage($user, $group);
    }

    public function restore(User $user, Group $group): bool
    {
        return false;
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return false;
    }

    protected function canManage(User $user, Group $group): bool
    {
        if ($user->hasAnyRole(['center_admin', 'teacher', 'assistant'])) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            if ($centerId && $group->center_id === $centerId) {
                return true;
            }
            if ($group->center && $group->center->user_id === $user->id) {
                return true;
            }
        }

        return $group->teacher_id === $user->id;
    }
}
