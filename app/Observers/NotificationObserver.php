<?php

namespace App\Observers;

use App\Models\Notification;
use App\Events\NotificationCreated;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        broadcast(new NotificationCreated($notification, $notification->notifiable_id))
            ->toOthers();
    }
}
