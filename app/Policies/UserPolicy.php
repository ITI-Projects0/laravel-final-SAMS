<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'center_admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin and center admin can view users
        if ($user->hasAnyRole(['admin', 'center_admin'])) {
            return true;
        }

        // Users can view themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'center_admin', 'teacher', 'assistant']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Center admin can update users in their center
        if ($user->hasRole('center_admin')) {
            return $model->center_id === $user->ownedCenter?->id;
        }

        // Admin can update anyone
        if ($user->hasRole('admin')) {
            return true;
        }

        // Teachers/Assistants can update users in their center
        if ($user->hasAnyRole(['teacher', 'assistant'])) {
            return $model->center_id === $user->center_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Center admin can delete users in their center
        if ($user->hasRole('center_admin')) {
            return $model->center_id === $user->ownedCenter?->id;
        }

        // Only admin can delete
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }
}
