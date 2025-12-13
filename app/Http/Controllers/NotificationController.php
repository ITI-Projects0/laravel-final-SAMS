<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success(
            data: \App\Http\Resources\NotificationResource::collection($notifications),
            message: 'Notifications retrieved successfully.',
            meta: [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ]
        );
    }

    /**
     * Get latest 10 notifications
     */
    public function latest()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $this->success(
            data: \App\Http\Resources\NotificationResource::collection($notifications),
            message: 'Latest notifications retrieved successfully.'
        );
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount()
    {
        $user = Auth::user();

        $count = $user->unreadNotifications()->count();

        return $this->success(
            data: ['count' => $count],
            message: 'Unread notifications count retrieved successfully.'
        );
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);

        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return $this->success(
            data: new \App\Http\Resources\NotificationResource($notification),
            message: 'Notification marked as read'
        );
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return $this->success(
            message: 'All notifications marked as read'
        );
    }

    /**
     * Delete a specific notification
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return $this->success(
            message: 'Notification deleted'
        );
    }

    /**
     * Delete all notifications
     */
    public function destroyAll()
    {
        $user = Auth::user();

        $user->notifications()->delete();

        return $this->success(
            message: 'All notifications deleted'
        );
    }
}
