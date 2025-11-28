<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Mail\ActivationCodeMail;
use App\Mail\IncompleteProfileWarningMail;
use App\Mail\ResetCodeMail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Cache;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $activationCode = (string) Str::uuid();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'activation_code' => $activationCode,
            'is_data_complete' => false,
            'status' => 'pending',
        ]);

        // Send Activation Code
        Mail::to($user->email)->send(new ActivationCodeMail($user, $activationCode));

        // Send Incomplete Profile Warning
        Mail::to($user->email)->send(new IncompleteProfileWarningMail($user));

        $token = $user->createToken('sams-app')->plainTextToken;

        return $this->success(
            data: [
                'user' => $user->only(['id', 'name', 'email', 'role', 'phone', 'status']),
                'token' => $token,
            ],
            message: 'Registration successful.',
            status: 201
        );
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = User::where('activation_code', $request->code)->first();

        if (!$user) {
            return $this->error('Activation link is invalid or has already been used.', 404);
        }

        if ($user->email_verified_at) {
            return $this->success(message: 'Email is already verified.');
        }

        $user->email_verified_at = now();
        $user->activation_code = null;
        $user->status = 'active';
        $user->save();

        return $this->success(
            data: ['user' => $user->only(['id', 'name', 'email', 'status'])],
            message: 'Email verified successfully.'
        );
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->error('The provided credentials are incorrect.', 401);
        }

        $user = User::find(Auth::id());

        if ($user->status !== 'active') {
            return $this->error('Account is not active.', 403);
        }

        if ($user->tokens()->count()) {
            return $this->error('User is already logged in from another device.', 403);
        }

        $token = $user->createToken('sams-app')->plainTextToken;

        return $this->success(
            data: [
                'user' => $user->only(['id', 'name', 'email', 'phone', 'status']),
                'token' => $token,
            ],
            message: 'Login successful.'
        );
    }

    public function logout()
    {
        $user = User::findOrFail(Auth::id());
        $user->tokens()->delete();

        return $this->success(message: 'Logout successful.');
    }

    public function me()
    {
        return $this->success(data: Auth::user());
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return $this->error('Google login failed.', 400);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(16)),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'status' => 'active',
                'is_data_complete' => false,
            ]);

            Mail::to($user->email)->send(new IncompleteProfileWarningMail($user));
        } else {
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->save();
            }
        }

        $token = $user->createToken('sams-app')->plainTextToken;

        // SECURE: Generate a short-lived code to exchange for the token
        $exchangeCode = Str::random(40);
        Cache::put('auth_exchange_' . $exchangeCode, [
            'token' => $token,
            'user_id' => $user->id
        ], now()->addMinutes(1));

        $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
        $queryParams = http_build_query(['code' => $exchangeCode]);

        return redirect()->to("{$frontendUrl}/login?{$queryParams}");
    }

    public function exchangeToken(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $data = Cache::pull('auth_exchange_' . $request->code);

        if (!$data) {
            return $this->error('Invalid or expired exchange code.', 400);
        }

        $user = User::find($data['user_id']);

        return $this->success(
            data: [
                'token' => $data['token'],
                'user' => $user->only(['id', 'name', 'email', 'role', 'status', 'is_data_complete']),
            ],
            message: 'Login successful.'
        );
    }

    public function completeProfile(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'role' => 'required|string|in:student,parent,teacher,center_admin,assistant',
        ]);

        $user = Auth::user();
        $user->update([
            'phone' => $request->phone,
            'role' => $request->role,
            'is_data_complete' => true,
        ]);

        return $this->success(
            data: ['user' => $user],
            message: 'Profile completed successfully.'
        );
    }

    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $plainToken = (string) Str::uuid();

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        // SECURE: Generate a short-lived exchange code
        $exchangeCode = Str::random(40);
        Cache::put('password_reset_exchange_' . $exchangeCode, [
            'email' => $request->email,
            'token' => $plainToken
        ], now()->addMinutes(30));

        Mail::to($request->email)->send(new ResetCodeMail($exchangeCode));

        return $this->success(message: 'Password reset link has been emailed to you.');
    }

    public function validateResetCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $data = Cache::get('password_reset_exchange_' . $request->code);

        if (!$data) {
            return $this->error('Invalid or expired reset link.', 400);
        }

        return $this->success(data: [
            'email' => $data['email'],
            'token' => $data['token']
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return $this->error('Invalid or expired reset link.', 400);
        }

        if (now()->diffInMinutes($record->created_at) > 30) {
            return $this->error('Reset link expired.', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->success(message: 'Password reset successfully.');
    }
}
