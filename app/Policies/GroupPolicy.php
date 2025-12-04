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
        return $user->id === $group->teacher_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'center_admin', 'teacher', 'assistant'])
            || in_array($user->role, ['admin', 'center_admin', 'teacher', 'assistant'], true);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->id === $group->teacher_id;
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->id === $group->teacher_id;
    }

    public function restore(User $user, Group $group): bool
    {
        return false;
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return false;
    }
}
