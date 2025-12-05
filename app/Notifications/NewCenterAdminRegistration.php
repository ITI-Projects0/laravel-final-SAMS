<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewCenterAdminRegistration extends Notification implements ShouldQueue
{
    use Queueable;

    public $centerAdmin;

    public function __construct(User $centerAdmin)
    {
        $this->centerAdmin = $centerAdmin;
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
            'type' => 'new_center_admin_registration',
            'title' => 'New Center Admin Registration',
            'message' => "New center admin ({$this->centerAdmin->name}) has registered and is awaiting approval",
            'center_admin_id' => $this->centerAdmin->id,
            'center_admin_name' => $this->centerAdmin->name,
            'center_admin_email' => $this->centerAdmin->email,
            'icon' => 'user-plus',
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
