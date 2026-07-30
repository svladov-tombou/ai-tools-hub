<?php

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' '.fake()->randomElement(['AI', 'Assistant', 'Studio', 'Copilot']),
            'description' => fake()->paragraph(2),
            'url' => 'https://'.fake()->domainName(),
            'documentation_url' => 'https://'.fake()->domainName().'/docs',
            'video_url' => 'https://'.fake()->domainName().'/demo',
            'difficulty' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'status' => 'published',
            'created_by' => null,
        ];
    }
}
