<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactNotification;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\NewContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * Store a new contact message and send email notification.
     */
    public function store(\App\Http\Requests\StoreContactRequest $request)
    {
        try {
            $validated = $request->validated();

            $contact = Contact::create($validated);
            $admins = User::role('admin')->get();

            // Push in-app notification to all admins
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewContactMessage($contact));
            }

            // Send email notification to admin
            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new ContactNotification($contact));
            }

            return $this->success(message: 'Your message has been sent successfully.');
        } catch (\Exception $e) {
            return $this->error(message: 'Failed to send your message. Please try again later.', status: 500);
        }
    }

    /**
     * Get all contact messages (admin only).
     */
    public function index()
    {
        $contacts = Contact::orderBy('created_at', 'desc')->get();

        return $this->success(
            data: \App\Http\Resources\ContactResource::collection($contacts),
            message: 'Contact messages retrieved successfully.'
        );
    }
}
