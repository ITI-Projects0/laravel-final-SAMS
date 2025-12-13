<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
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
    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $group = $lesson->group;
        if (!$group) return false;

        // Center Admin & Assistant: View all lessons in their center
        if ($user->hasAnyRole(['center_admin', 'assistant'])) {
            $centerId = $user->center_id ?? $user->ownedCenter?->id;
            return $centerId && $group->center_id === $centerId;
        }

        // Teacher: View ONLY their own group's lessons
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Student: View if member of the group
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
    public function update(User $user, Lesson $lesson): bool
    {
        // Admin: NO
        if ($user->hasRole('admin')) {
            return false;
        }

        $group = $lesson->group;
        if (!$group) return false;

        // Center Admin: NO
        if ($user->hasRole('center_admin')) {
            return false;
        }

        // Teacher: Update ONLY their own group's lessons
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Assistant: Can edit any lesson in their center
        if ($user->hasRole('assistant')) {
            $centerId = $user->center_id;
            return $centerId && $group->center_id === $centerId;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        // Admin: NO
        if ($user->hasRole('admin')) {
            return false;
        }

        $group = $lesson->group;
        if (!$group) return false;

        // Teacher: Can delete lessons in their OWN group
        if ($user->hasRole('teacher')) {
            return $group->teacher_id === $user->id;
        }

        // Center Admin: NO
        // Assistant: NO
        return false;
    }

    public function restore(User $user, Lesson $lesson): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return $user->hasRole('admin');
    }
}
