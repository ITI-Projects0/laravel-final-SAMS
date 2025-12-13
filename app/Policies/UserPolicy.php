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
        return $user->hasAnyRole(['admin', 'center_admin', 'teacher', 'assistant']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin: View all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Center Admin: View all in center
        if ($user->hasRole('center_admin')) {
            return $model->center_id === $user->ownedCenter?->id || $model->id === $user->id;
        }

        // Teacher/Assistant: View all in center
        if ($user->hasAnyRole(['teacher', 'assistant'])) {
            return $model->center_id === $user->center_id || $model->id === $user->id;
        }

        // Users can view themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin: Yes
        // Center Admin: Yes (Teachers, Assistants ONLY - enforced in controller)
        // Teacher: Yes (Students, Parents ONLY - enforced in controller)
        // Assistant: Yes (Students, Parents ONLY - enforced in controller)
        return $user->hasAnyRole(['admin', 'center_admin', 'teacher', 'assistant']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admin: Yes
        if ($user->hasRole('admin')) {
            return true;
        }

        // Self update
        if ($user->id === $model->id) {
            return true;
        }

        // Center Admin: 
        // - Can edit Teachers/Assistants in center
        // - CANNOT edit Students/Parents (per requirements: "Delete Students... Does not add them" - implies no edit either?)
        //   Prompt says: "Center Admin ... Create - Edit - Delete Teachers and Assistants + Delete Students and Parents."
        //   It does NOT explicitly say "Edit Students". It lists "Delete" for them.
        //   I will restrict editing to Teachers/Assistants.
        if ($user->hasRole('center_admin')) {
            $isCenterMember = $model->center_id === $user->ownedCenter?->id;
            if (!$isCenterMember) return false;

            return $model->hasAnyRole(['teacher', 'assistant']);
        }

        // Teacher/Assistant: 
        // - Prompt says "Perfect" for them, which previously meant "Add Students/Parents".
        // - Usually "Add" implies "Edit" if they made a mistake?
        // - Previous policy was "No edit others".
        // - I will keep it as "No edit others" unless they are creating.
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Admin: Yes
        if ($user->hasRole('admin')) {
            return true;
        }

        // Center Admin: 
        // - Can delete Teachers/Assistants
        // - Can delete Students/Parents
        if ($user->hasRole('center_admin')) {
            $isCenterMember = $model->center_id === $user->ownedCenter?->id;
            if (!$isCenterMember) return false;

            // Can delete anyone in their center (except maybe themselves, but that's self-delete)
            return true;
        }

        // Teacher: NO delete
        // Assistant: NO delete
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }
}
