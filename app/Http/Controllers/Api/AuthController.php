<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'phone'    => $request->phone,
            'status'   => 'active',
        ]);

        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $user->only(['id', 'name', 'email', 'role', 'phone', 'status']),
            'token'   => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid login credentials.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user->only(['id', 'name', 'email', 'role', 'phone', 'status']),
            'token'   => $token,
        ]);
    }

    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout successful.']);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }
}
