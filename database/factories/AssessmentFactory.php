<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
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
            'group_id' => Group::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(['exam', 'quiz', 'assignment']),
            'max_score' => fake()->numberBetween(50, 100),
            'scheduled_at' => fake()->date(),
        ];
    }
}
