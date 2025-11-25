<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
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
            'student_id' => User::factory()->state(['role' => 'student']),
            'date' => fake()->date(),
            'status' => fake()->randomElement(['present', 'absent', 'late', 'excused']),
            'marked_by' => User::factory()->state(['role' => 'teacher']),
            'created_at' => now(),
        ];
    }
}
