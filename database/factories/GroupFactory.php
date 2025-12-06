<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'teacher_id' => User::factory()->teacher(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            // 'join_code' => Str::upper(Str::random(6)),
            'subject' => fake()->randomElement(['Math', 'Science', 'English', 'History']),
            // 'is_approval_required' => fake()->boolean(30),
            'is_active' => fake()->boolean(90),
            'schedule_days' => fake()->randomElements(['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 2),
            'schedule_time' => fake()->time('H:i'),
            'sessions_count' => fake()->numberBetween(8, 24),
            'academic_year' => fake()->randomElement(['2023-2024', '2024-2025']),
        ];
    }
}
