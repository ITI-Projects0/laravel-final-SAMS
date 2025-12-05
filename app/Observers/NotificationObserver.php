<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use Illuminate\Notifications\DatabaseNotification;

class NotificationObserver
{
    /**
     * Handle the DatabaseNotification "created" event.
     */
    public function created(DatabaseNotification $notification): void
    {
        // Broadcast the notification event
        broadcast(new NotificationCreated($notification, $notification->notifiable_id))->toOthers();
    }
}
