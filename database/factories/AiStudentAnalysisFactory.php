<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiStudentAnalysis>
 */
class AiStudentAnalysisFactory extends Factory
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
            'student_id' => User::factory()->student(),
            'requested_by' => User::factory(),
            'summary' => fake()->sentence(8),
            'details' => fake()->paragraph(),
        ];
    }
}
