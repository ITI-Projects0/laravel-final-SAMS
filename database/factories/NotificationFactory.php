<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
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
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'related_student_id' => User::factory()->state(['role' => 'student']),
            'related_group_id' => Group::factory(),
            'type' => fake()->randomElement(['low_performance', 'attendance', 'general']),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'is_read' => fake()->boolean(30),
        ];
    }
}
