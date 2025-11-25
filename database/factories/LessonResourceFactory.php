<?php

namespace Database\Factories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LessonResource>
 */
class LessonResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => fake()->randomElement(['video', 'file', 'link']),
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'created_at' => now(),
        ];
    }
}
