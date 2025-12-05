<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $password;
    public $centerAdmin;

    public function __construct(string $password, User $centerAdmin)
    {
        $this->password = $password;
        $this->centerAdmin = $centerAdmin;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to SAMS - Your Account Has Been Created')
            ->greeting("Hello {$notifiable->name},")
            ->line("A teacher account has been created for you in SAMS by {$this->centerAdmin->name}.")
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
