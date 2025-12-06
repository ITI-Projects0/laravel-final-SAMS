<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentAbsent extends Notification implements ShouldQueue
{
    use Queueable;

    public $student;
    public $group;
    public $date;

    public function __construct(User $student, Group $group, string $date)
    {
        $this->student = $student;
        $this->group = $group;
        $this->date = $date;
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
            'type' => 'student_absent',
            'title' => 'Student Absent',
            'message' => "Student {$this->student->name} was absent in group {$this->group->name} on {$this->date}",
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'date' => $this->date,
            'icon' => 'exclamation-triangle',
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
