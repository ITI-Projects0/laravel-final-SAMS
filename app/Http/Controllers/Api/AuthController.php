<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone'],
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
        $user = Auth::attempt($request->only('email', 'password'));

        if (! $user) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        $user = User::find(Auth::id());

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        if ($user->tokens()->count()) {
            return response()->json(['message' => 'User is already logged in from another device.'], 403);
        }

        $token = $user->createToken('sams-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user->only(['id', 'name', 'email', 'phone', 'status']),
            'token'   => $token,
        ]);
    }

    public function logout()
    {
        $user = User::findOrFail(Auth::id());
        $user->tokens()->delete();

        return response()->json(['message' => 'Logout successful.']);
    }
    public function me()
    {
        return response()->json(Auth::user());
    }
    }
