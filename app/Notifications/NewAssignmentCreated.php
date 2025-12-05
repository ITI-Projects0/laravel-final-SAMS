<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Group;
use App\Models\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewAssignmentCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $student;
    public $assignment;
    public $group;

    public function __construct(User $student, Assessment $assignment, Group $group)
    {
        $this->student = $student;
        $this->assignment = $assignment;
        $this->group = $group;
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
            'type' => 'new_assignment_created',
            'title' => 'New Assignment',
            'message' => "A new assignment ({$this->assignment->title}) has been added for your child {$this->student->name} in group {$this->group->name}",
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'assignment_id' => $this->assignment->id,
            'assignment_title' => $this->assignment->title,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'due_date' => $this->assignment->due_date ?? null,
            'icon' => 'document-text',
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
