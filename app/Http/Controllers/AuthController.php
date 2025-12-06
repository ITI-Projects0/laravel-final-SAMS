<?php

namespace App\Http\Controllers;

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
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();

            $activationCode = (string) Str::uuid();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'activation_code' => $activationCode,
                'status' => 'active',
            ]);

            $guard = config('permission.defaults.guard', 'api');
            Role::firstOrCreate(['name' => 'center_admin', 'guard_name' => $guard]);
            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => $guard]);

            $user->assignRole('center_admin', 'teacher');

            $centerName = $validated['center_name'] ?? $user->name;

            $center = \App\Models\Center::create([
                'user_id' => $user->id,
                'name' => $centerName,
                'logo_url' => null,
                'primary_color' => '#2d3250',
                'secondary_color' => '#424769',
                'subdomain' => Str::slug($user->name) . '-' . $user->id,
                'is_active' => true,
            ]);

            $user->center()->associate($center);
            $user->center_id = $center->id;
            $user->save();

            // Send Activation Code
            Mail::to($user->email)->queue(new ActivationCodeMail($user, $activationCode));

            $token = $user->createToken('sams-app')->plainTextToken;

            DB::commit();

            return $this->success([
                'user' => array_merge(
                    $user->only(['id', 'name', 'email', 'phone', 'status']),
                    [
                        'roles' => $user->getRoleNames(),
                        'role' => $user->getRoleNames()->first(),
                    ]
                ),
                'token' => $token,
            ], 'Registration successful. Please check your email for the activation code.', 201);
        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(
                message: $e->getMessage(),
                errors: $e->getMessage(),
            );
        }

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
        $guard = config('permission.defaults.guard', 'api');
        $roleNames = ['center_admin', 'teacher'];

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Google login failed.'], 400);
        }

        // Ensure roles exist for this guard to prevent guard mismatch errors
        foreach ($roleNames as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
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
        } else {
            // Update google_id if not set
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->save();
            }
        }

        // Sync roles with correct guard
        $user->syncRoles($roleNames);

        // Generate a short-lived exchange token
        $exchangeToken = Str::random(40);
        Cache::put('auth_exchange_' . $exchangeToken, $user->id, 60); // Valid for 60 seconds

        // Redirect to frontend with exchange token
        $frontendUrl = config('app.frontend_url', 'http://localhost:35045');
        $queryParams = http_build_query(['exchange_token' => $exchangeToken]);
        return redirect()->to("{$frontendUrl}/login?{$queryParams}");
    }

    public function exchangeToken(Request $request)
    {
        $request->validate(['exchange_token' => 'required|string']);

        $userId = Cache::pull('auth_exchange_' . $request->exchange_token);

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
        $user = User::findOrFail(Auth::id());
        return $this->success(array_merge(
            $user->only(['id', 'name', 'email', 'phone', 'status', 'avatar']),
            ['roles' => $user->getRoleNames()]
        ));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return $this->success(array_merge(
            $user->only(['id', 'name', 'email', 'phone', 'status', 'avatar']),
            ['roles' => $user->getRoleNames()]
        ), 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->error('The current password is incorrect.', 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return $this->success(null, 'Password updated successfully.');
    }

    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $plainToken = (string) Str::uuid();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        // Queue reset mail to keep request fast
        Mail::to($request->email)->queue(new ResetCodeMail($plainToken, $request->email));

        return response()->json(['message' => 'Password reset link has been emailed to you.']);
    }

    // New endpoint to validate reset code and return a token for password reset
    public function validateResetCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        // Find a matching record where the hashed token matches the provided code
        $record = DB::table('password_reset_tokens')->where('created_at', '>=', now()->subMinutes(30))->first();
        if (!$record || !Hash::check($request->code, $record->token)) {
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

        $record = DB::table('password_reset_tokens')
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
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
