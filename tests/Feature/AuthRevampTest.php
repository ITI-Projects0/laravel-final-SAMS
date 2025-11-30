<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\ActivationCodeMail;
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
            'phone' => '1234567890',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->activation_code);

        Mail::assertSent(ActivationCodeMail::class);
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

    public function test_password_can_be_reset_via_secure_link()
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => Hash::make('Oldpass1!'),
        ]);

        $this->postJson('/api/auth/send-reset-code', [
            'email' => $user->email,
        ])->assertOk();

        $exchangeToken = null;
        Mail::assertSent(ResetCodeMail::class, function (ResetCodeMail $mail) use (&$exchangeToken) {
            $exchangeToken = $mail->token;
            return true;
        });
        $this->assertNotNull($exchangeToken, 'Reset token should be captured');

        $validateResponse = $this->postJson('/api/auth/validate-reset-code', ['code' => $exchangeToken]);
        $validateResponse->assertOk();
        $data = $validateResponse->json('data');
        $this->assertArrayHasKey('token', $data);
        $resetToken = $data['token'];

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'N3wpass!1',
            'password_confirmation' => 'N3wpass!1',
        ])->assertOk();

        $this->assertTrue(Hash::check('N3wpass!1', $user->fresh()->password));
    }
}
