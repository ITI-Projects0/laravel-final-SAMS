<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CenterPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin only – View all centers
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view the model.
     * Admin can view any center
     * Center admin can view ONLY his own center
     */
    public function view(User $user, Center $center): bool
    {
        return $this->isAdmin($user)
            || ($this->isCenterAdmin($user) && $center->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // return $this->isAdmin($user);
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Center $center): bool
    {
        return $this->isAdmin($user)
        || ($this->isCenterAdmin($user) && $center->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Center $center): bool
    {
        return $this->isAdmin($user)
            || ($this->isCenterAdmin($user) && $center->user_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Center $center): bool
    {
        // return $this->isAdmin($user);
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Center $center): bool
    {
        return $this->isAdmin($user)
            || ($this->isCenterAdmin($user) && $center->user_id === $user->id);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->role === 'admin';
    }

    private function isCenterAdmin(User $user): bool
    {
        return $user->hasRole('center_admin') || $user->role === 'center_admin';
    }
}
