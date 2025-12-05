<?php

namespace App\Notifications;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GroupUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $group;
    public $changes;

    public function __construct(Group $group, array $changes)
    {
        $this->group = $group;
        $this->changes = $changes;
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
        $changesList = $this->formatChanges();

        return [
            'type' => 'group_updated',
            'title' => 'Group Updated',
            'message' => "Group {$this->group->name} has been updated: {$changesList}",
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'changes' => $this->changes,
            'icon' => 'pencil-square',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Format changes for display.
     */
    private function formatChanges(): string
    {
        $formatted = [];
        $labels = [
            'name' => 'Name',
            'description' => 'Description',
            'subject' => 'Subject',
            'schedule' => 'Schedule',
        ];

        foreach ($this->changes as $key => $value) {
            if (isset($labels[$key])) {
                $formatted[] = $labels[$key];
            }
        }

        return implode(', ', $formatted);
    }

    /**
     * Determine if notification should be sent after database transaction commits.
     */
    public function afterCommit(): bool
    {
        return true;
    }
}
