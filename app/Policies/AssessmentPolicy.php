<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssessmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered in controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $group = $assessment->group;
        if (!$group)
            return false;

        // Center Admin & Assistant: View all in center
        if ($user->hasAnyRole(['center_admin', 'assistant'])) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            return $centerId && $group->center_id === $centerId;
        }

        // Teacher: View ONLY their own group's assessments
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Student: View if member
        if ($user->hasRole('student')) {
            return $group->students()->where('users.id', $user->id)->exists();
        }

        // Parent: View if child is member
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
        // Admin: NO
        // Center Admin: NO
        // Teacher: YES
        // Assistant: YES
        return $user->hasAnyRole(['teacher', 'assistant']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assessment $assessment): bool
    {
        // Admin: NO
        if ($user->hasRole('admin')) {
            return false;
        }

        $group = $assessment->group;
        if (!$group)
            return false;

        // Center Admin: NO
        if ($user->hasRole('center_admin')) {
            return false;
        }

        // Teacher: Update ONLY their own group's assessments
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Assistant: Can edit any assessment in their center
        if ($user->hasRole('assistant')) {
            $centerId = $user->center_id;
            return $centerId && $group->center_id === $centerId;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assessment $assessment): bool
    {
        // Admin: NO
        if ($user->hasRole('admin')) {
            return false;
        }

        $group = $assessment->group;
        if (!$group)
            return false;

        // Teacher: Can delete assessments in their OWN group
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Center Admin: NO
        // Assistant: NO
        return false;
    }

    public function restore(User $user, Assessment $assessment): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Assessment $assessment): bool
    {
        return $user->hasRole('admin');
    }
}
