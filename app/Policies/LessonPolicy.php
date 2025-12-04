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
        // Admin, center_admin, teacher, assistant can view lessons
        return $user->hasRole(['admin', 'center_admin', 'teacher', 'assistant']) || in_array($user->role, [
            'admin',
            'center_admin',
            'teacher',
            'assistant',
        ], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        return $this->canAccessGroup($user, $lesson);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Teacher, assistant, center_admin, admin can create lessons
        return $user->hasRole(['admin', 'center_admin', 'teacher', 'assistant']) || in_array($user->role, [
            'admin',
            'center_admin',
            'teacher',
            'assistant',
        ], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        return $this->canAccessGroup($user, $lesson);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->canAccessGroup($user, $lesson);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lesson $lesson): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return false;
    }

    protected function canAccessGroup(User $user, Lesson $lesson): bool
    {
        if ($user->hasRole('admin') || $user->role === 'admin') {
            return true;
        }

        $group = $lesson->group;

        if (!$group) {
            return false;
        }

        // Teacher of the group
        if ($group->teacher_id === $user->id) {
            return true;
        }

        // Center admin of the center that owns the group
        if (($user->hasRole('center_admin') || $user->role === 'center_admin') && $group->center?->user_id === $user->id) {
            return true;
        }

        // Assistants: we allow assistants who are students/assistants in same center via groups relation
        if ($user->hasRole('assistant') || $user->role === 'assistant') {
            return $user->groups()->where('center_id', $group->center_id)->exists();
        }

        return false;
    }
}
