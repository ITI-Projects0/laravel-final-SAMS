<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign a specific Spatie role after creating the user.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => config('permission.defaults.guard')]);
            $user->assignRole($role);
        });
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function centerAdmin(): static
    {
        return $this->withRole('center_admin');
    }

    public function teacher(): static
    {
        return $this->withRole('teacher');
    }

    public function assistant(): static
    {
        return $this->withRole('assistant');
    }

    public function student(): static
    {
        return $this->withRole('student');
    }

    public function parentRole(): static
    {
        return $this->withRole('parent');
    }
}
