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
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'nullable|string|max:255',
                'message' => 'required|string|min:10',
            ]);

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

        return response()->json([
            'data' => $contacts,
        ]);
    }
}
