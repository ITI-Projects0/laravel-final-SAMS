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
use App\Mail\ResetCodeMail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $activationCode = (string) Str::uuid();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'activation_code' => $activationCode,
            'status' => 'active',
        ]);

        $user->assignRole('center_admin', 'teacher');

        // Send Activation Code
        Mail::to($user->email)->sendNow(new ActivationCodeMail($user, $activationCode));

        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Please check your email for the activation code.',
            'user' => array_merge(
                $user->only(['id', 'name', 'email', 'phone', 'status']),
                ['roles' => $user->getRoleNames()]
            ),
            'token' => $token,
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = User::where('activation_code', $request->code)->first();

        if (!$user) {
            return response()->json(['message' => 'Activation link is invalid or has already been used.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        $user->email_verified_at = now();
        $user->activation_code = null;
        $user->status = 'active';
        $user->save();

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user->only(['id', 'name', 'email', 'status']),
        ]);
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
            return response()->json(['message' => 'Google login failed.'], 400);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(16)), // Random password
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'status' => 'active',
            ]);
            $user->assignRole('center_admin', 'teacher');
        } else {
            // Update google_id if not set
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->save();
            }

            // Ensure user has a role (fix for existing users with no role)
            if ($user->getRoleNames()->isEmpty()) {
                $user->assignRole('center_admin', 'teacher');
            }
        }

        // Generate a short-lived exchange token
        $exchangeToken = Str::random(40);
        \Cache::put('auth_exchange_' . $exchangeToken, $user->id, 60); // Valid for 60 seconds

        // Redirect to frontend with exchange token
        $frontendUrl = config('app.frontend_url', 'http://localhost:35045');
        $queryParams = http_build_query(['exchange_token' => $exchangeToken]);
        return redirect()->to("{$frontendUrl}/login?{$queryParams}");
    }

    public function exchangeToken(Request $request)
    {
        $request->validate(['exchange_token' => 'required|string']);

        $userId = \Cache::pull('auth_exchange_' . $request->exchange_token);

        if (!$userId) {
            return response()->json(['message' => 'Invalid or expired exchange token.'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
             return response()->json(['message' => 'User not found.'], 404);
        }

        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => array_merge(
                $user->only(['id', 'name', 'email', 'phone', 'status']),
                ['roles' => $user->getRoleNames()]
            ),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = Auth::attempt($request->only('email', 'password'));

        if (!$user) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        $user = User::find(Auth::id());

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }



        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => array_merge(
                $user->only(['id', 'name', 'email', 'phone', 'status']),
                ['roles' => $user->getRoleNames()]
            ),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout successful.']);
    }
    public function me()
    {
        $user = Auth::user();
        return response()->json(array_merge(
            $user->only(['id', 'name', 'email', 'phone', 'status']),
            ['roles' => $user->getRoleNames()]
        ));
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

        Mail::to($request->email)->send(new ResetCodeMail($plainToken, $request->email));

        return response()->json(['message' => 'Password reset link has been emailed to you.']);
    }

    // New endpoint to validate reset code and return a token for password reset
    public function validateResetCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        // Find a matching record where the hashed token matches the provided code
        $record = \DB::table('password_reset_tokens')->where('created_at', '>=', now()->subMinutes(30))->first();
        if (!$record || !\Hash::check($request->code, $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 400);
        }

        // Return the plain code as a token that can be used in resetPassword
        return response()->json(['data' => ['token' => $request->code]]);
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
            return response()->json(['message' => 'Invalid or expired reset link.'], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 30) {
            return response()->json(['message' => 'Reset link expired.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete token
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
