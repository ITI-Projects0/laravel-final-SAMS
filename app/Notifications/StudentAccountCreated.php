<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $password;
    public $createdBy;

    public function __construct(string $password, User $createdBy)
    {
        $this->password = $password;
        $this->createdBy = $createdBy;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification (for database).
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'student_account_created',
            'title' => 'Welcome to SAMS',
            'message' => 'Your account has been created successfully. You can now login and join groups.',
            'icon' => 'academic-cap',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to SAMS - Your Account Has Been Created')
            ->greeting("Hello {$notifiable->name},")
            ->line('A student account has been created for you in SAMS.')
            ->line('Your login credentials:')
            ->line("Email: {$notifiable->email}")
            ->line("Password: {$this->password}")
            ->line('Please change your password after your first login.')
            ->action('Login', url('/login'))
            ->line('Thank you for joining SAMS!');
    }

    /**
     * Determine if notification should be sent after database transaction commits.
     */
    public function afterCommit(): bool
    {
        return true;
    }
}
