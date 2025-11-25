<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Center>
 */
class CenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'center_admin']),
            'name' => fake()->company() . ' Academy',
            'logo_url' => fake()->imageUrl(300, 300, 'education', true),
            'primary_color' => fake()->safeHexColor(),
            'secondary_color' => fake()->safeHexColor(),
            'subdomain' => fake()->unique()->domainWord(),
            'is_active' => true,
        ];
    }
}
