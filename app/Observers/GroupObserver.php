<?php

namespace App\Observers;

use App\Models\Group;

class GroupObserver
{
    public function created(Group $group): void
    {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'subject_type' => 'Group',
            'subject_id' => $group->id,
            'description' => "New group created: {$group->name}",
            'ip_address' => request()->ip(),
            'properties' => $group->toArray(),
        ]);
    }

    public function updated(Group $group): void
    {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'subject_type' => 'Group',
            'subject_id' => $group->id,
            'description' => "Group updated: {$group->name}",
            'ip_address' => request()->ip(),
            'properties' => $group->getChanges(),
        ]);
    }

    public function deleted(Group $group): void
    {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'subject_type' => 'Group',
            'subject_id' => $group->id,
            'description' => "Group deleted: {$group->name}",
            'ip_address' => request()->ip(),
            'properties' => $group->toArray(),
        ]);
    }
}
