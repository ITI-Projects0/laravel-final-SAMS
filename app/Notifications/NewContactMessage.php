<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewContactMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public Contact $contact;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
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
            'type' => 'contact_message',
            'title' => 'New Contact Message',
            'message' => "New contact message from {$this->contact->name} ({$this->contact->email})",
            'contact_id' => $this->contact->id,
            'contact_name' => $this->contact->name,
            'contact_email' => $this->contact->email,
            'contact_subject' => $this->contact->subject,
            'contact_message' => $this->contact->message,
            'contact_message_preview' => Str::limit($this->contact->message, 160),
            'icon' => 'envelope',
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
