<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\ActivationCodeMail;
use App\Mail\IncompleteProfileWarningMail;
use App\Mail\ResetCodeMail;
use Illuminate\Support\Facades\Hash;

class AuthRevampTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_and_sends_emails()
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'is_data_complete' => false,
            'status' => 'pending',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->activation_code);

        Mail::assertSent(ActivationCodeMail::class);
        Mail::assertSent(IncompleteProfileWarningMail::class);
    }

    public function test_verification_verifies_email()
    {
        $user = User::factory()->create([
            'activation_code' => '123456',
            'status' => 'pending',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'code' => '123456',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->activation_code);
        $this->assertEquals('active', $user->status);
    }

    public function test_cleanup_command_deletes_old_incomplete_users()
    {
        // Old incomplete user (should be deleted)
        User::factory()->create([
            'is_data_complete' => false,
            'created_at' => now()->subDays(4),
        ]);

        // Recent incomplete user (should be kept)
        User::factory()->create([
            'is_data_complete' => false,
            'created_at' => now()->subDays(2),
        ]);

        // Old complete user (should be kept)
        User::factory()->create([
            'is_data_complete' => true,
            'created_at' => now()->subDays(4),
        ]);

        $this->artisan('app:cleanup-incomplete-users')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_password_can_be_reset_via_secure_link()
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Oldpass1!'),
        ]);

        $this->postJson('/api/auth/send-reset-code', [
            'email' => $user->email,
        ])->assertOk();

        $token = null;
        Mail::assertSent(ResetCodeMail::class, function (ResetCodeMail $mail) use (&$token, $user) {
            $token = $mail->token;
            return $mail->email === $user->email;
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'N3wpass!1',
            'password_confirmation' => 'N3wpass!1',
        ])->assertOk();

        $this->assertTrue(Hash::check('N3wpass!1', $user->fresh()->password));
    }
}
