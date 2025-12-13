<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $userId;

    public function __construct($notification, $userId)
    {
        $this->notification = $notification;
        $this->userId = $userId;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('App.Models.User.' . $this->notification->notifiable_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->data['type'] ?? 'info',
            'title' => $this->notification->data['title'] ?? 'New Notification',
            'message' => $this->notification->data['message'] ?? '',
            'icon' => $this->notification->data['icon'] ?? 'bell',
            'created_at' => $this->notification->created_at?->toISOString(),
            'read_at' => $this->notification->read_at,
        ];
    }
}
