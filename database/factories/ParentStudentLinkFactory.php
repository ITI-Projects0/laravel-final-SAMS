<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ParentStudentLink>
 */
class ParentStudentLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => User::factory()->state(['role' => 'parent']),
            'student_id' => User::factory()->state(['role' => 'student']),
            'relationship' => fake()->randomElement(['father', 'mother', 'guardian']),
            'created_at' => now(),
        ];
    }
}
