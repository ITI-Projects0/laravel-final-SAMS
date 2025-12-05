<?php

use Illuminate\Support\Facades\Broadcast;

// Register broadcast routes with Sanctum auth
Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

// Private channel for individual users
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for all admins
Broadcast::channel('admin-channel', function ($user) {
    return $user->isAdmin();
});

// Private channel for specific group
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return $user->groups()->where('groups.id', $groupId)->exists()
        || $user->taughtGroups()->where('groups.id', $groupId)->exists();
});
