<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Center;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use App\Mail\ActivationCodeMail;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create roles
        Role::create(['name' => 'center_admin']);
        Role::create(['name' => 'teacher']);
        Role::create(['name' => 'student']);
    }

    public function test_registration_creates_user_center_roles_and_queues_email()
    {
        Mail::fake();
        // Queue::fake(); // Mail::fake() handles queuing checks for Mailable

        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'center_name' => 'Test Center',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'status', 'role'],
                    'token',
                ],
            ]);

        // Check User
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'active',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        // Check Center
        $this->assertDatabaseHas('centers', [
            'name' => 'Test Center',
            'user_id' => $user->id,
        ]);

        // Check Roles
        $this->assertTrue($user->hasRole('center_admin'));
        $this->assertTrue($user->hasRole('teacher'));

        // Check Email Queued
        Mail::assertQueued(ActivationCodeMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_registration_uses_user_name_for_center_if_center_name_missing()
    {
        Mail::fake();

        $payload = [
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'phone' => '0987654321',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            // center_name missing
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201);

        $user = User::where('email', 'test2@example.com')->first();

        // Check Center Name = User Name
        $this->assertDatabaseHas('centers', [
            'name' => 'Test User 2',
            'user_id' => $user->id,
        ]);
    }
}
