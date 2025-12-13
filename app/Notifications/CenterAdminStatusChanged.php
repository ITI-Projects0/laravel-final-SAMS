<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CenterAdminStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public $status;
    public $reason;
    public $userName;

    public function __construct(string $status, ?string $reason = null, ?string $userName = null)
    {
        $this->status = $status;
        $this->reason = $reason;
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable
            ? ['mail']
            : ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification (for database).
     */
    public function toArray($notifiable): array
    {
        $isApproved = $this->status === 'approved';

        return [
            'type' => 'center_admin_status_changed',
            'title' => $isApproved ? 'Application Approved' : 'Application Rejected',
            'message' => $isApproved
                ? 'Your center admin registration has been approved. You can now login and manage your center.'
                : "Your registration has been rejected" . ($this->reason ? ": {$this->reason}" : ''),
            'status' => $this->status,
            'reason' => $this->reason,
            'icon' => $isApproved ? 'check-circle' : 'x-circle',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $isApproved = $this->status === 'approved';
        
        $name = $this->userName;
        if (!$name && isset($notifiable->name)) {
            $name = $notifiable->name;
        }
        $name = $name ?? 'User';

        $mail = (new MailMessage)
            ->subject($isApproved ? 'Application Approved - SAMS' : 'Application Rejected - SAMS')
            ->greeting("Hello {$name},");

        if ($isApproved) {
            $mail->line('Your center admin registration has been approved.')
                ->line('You can now login and start managing your center.')
                ->action('Login', url('/login'))
                ->line('Thank you for using SAMS!');
        } else {
            $mail->line('We regret to inform you that your registration has been rejected.');
            if ($this->reason) {
                $mail->line("Reason: {$this->reason}");
            }
            $mail->line('If you have any questions, please contact administration.');
        }

        return $mail;
    }

    /**
     * Determine if notification should be sent after database transaction commits.
     */
    public function afterCommit(): bool
    {
        return true;
    }
}
