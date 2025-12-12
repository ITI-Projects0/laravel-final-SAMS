<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Group $group): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Center Admin & Assistant: View all groups in their center
        if ($user->hasAnyRole(['center_admin', 'assistant'])) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            return $centerId && $group->center_id === $centerId;
        }

        // Teacher: View ONLY their own groups
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Student: View if they are a member
        if ($user->hasRole('student')) {
            return $group->students()->where('users.id', $user->id)->exists();
        }

        // Parent: View if one of their children is a member
        if ($user->hasRole('parent')) {
            $childrenIds = $user->students()->pluck('users.id')->toArray();
            return $group->students()->whereIn('users.id', $childrenIds)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin: NO (View only)
        // Center Admin: NO
        // Assistant: NO
        // Teacher: YES
        return $user->hasRole('teacher');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        // Admin: NO
        if ($user->hasRole('admin')) {
            return false;
        }

        // Center Admin: NO
        if ($user->hasRole('center_admin')) {
            return false;
        }

        // Teacher: Update ONLY their own groups
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Assistant: Can edit any group in their center
        if ($user->hasRole('assistant')) {
            $centerId = $user->center_id;
            return $centerId && $group->center_id === $centerId;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        // Admin: NO (Only manages Center entity)
        if ($user->hasRole('admin')) {
            return false;
        }

        // Center Admin: YES (Can delete groups in their center)
        if ($user->hasRole('center_admin')) {
            $centerId = $user->ownedCenter?->id;
            return $centerId && $group->center_id === $centerId;
        }

        // Teacher: NO
        // Assistant: NO
        return false;
    }

    public function restore(User $user, Group $group): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return $user->hasRole('admin');
    }
}
