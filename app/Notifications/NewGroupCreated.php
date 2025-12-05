<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewGroupCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $group;
    public $teacher;

    public function __construct(Group $group, User $teacher)
    {
        $this->group = $group;
        $this->teacher = $teacher;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification (for database).
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_group_created',
            'title' => 'New Group Created',
            'message' => "Teacher {$this->teacher->name} has created a new group: {$this->group->name}",
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'group_subject' => $this->group->subject,
            'teacher_id' => $this->teacher->id,
            'teacher_name' => $this->teacher->name,
            'icon' => 'user-group',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Determine if notification should be sent after database transaction commits.
     */
    public function afterCommit(): bool
    {
        return true;
    }
}
