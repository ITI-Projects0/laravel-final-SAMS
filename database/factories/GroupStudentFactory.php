<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupStudent>
 */
class GroupStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'student_id' => User::factory()->student(),
            'is_pay' => fake()->boolean(60),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'joined_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
