<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Check if we have an authenticated user (admin creating a user)
        // If not, it might be self-registration
        $performer = auth()->user();

        \App\Models\ActivityLog::create([
            'user_id' => $performer ? $performer->id : $user->id, // If self-reg, attribute to self? Or null? Let's say null if not logged in, but wait, if they register they are the user.
            // Actually for self-registration, auth()->user() is null until they login.
            // But if an admin creates them, auth()->user() is the admin.
            'action' => 'create',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'description' => "New user registered: {$user->name}",
            'ip_address' => request()->ip(),
            'properties' => $user->toArray(),
        ]);
    }

    public function updated(User $user): void
    {
        // Ignore if only remember_token or timestamps changed
        if ($user->wasChanged(['remember_token', 'updated_at', 'email_verified_at', 'last_login_at'])) {
            return;
        }

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'description' => "User updated: {$user->name}",
            'ip_address' => request()->ip(),
            'properties' => $user->getChanges(),
        ]);
    }

    public function deleted(User $user): void
    {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'description' => "User deleted: {$user->name}",
            'ip_address' => request()->ip(),
            'properties' => $user->toArray(),
        ]);
    }
}
