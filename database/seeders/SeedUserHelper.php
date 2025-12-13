<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SeedUserHelper
{
    public static function createUser(array $attributes, Role $role, string $password = null): User
    {
        $name = $attributes['name'] ?? fake()->name();
        $email = $attributes['email'] ?? self::makeUniqueEmail($name);

        $payload = array_merge([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password ?? 'password'),
            'status' => $attributes['status'] ?? 'active',
            'approval_status' => $attributes['approval_status'] ?? 'approved',
            'phone' => $attributes['phone'] ?? fake()->e164PhoneNumber(),
        ], $attributes);

        $user = User::create($payload);
        $user->assignRole($role);

        return $user;
    }

    private static function makeUniqueEmail(string $name): string
    {
        $slug = Str::slug($name) ?: 'user';

        return "{$slug}." . uniqid() . '@example.com';
    }
}
