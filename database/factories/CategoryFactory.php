<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(12),
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement(['message-circle', 'code', 'help-circle', 'megaphone', 'coffee']),
            'position' => fake()->numberBetween(0, 20),
            'is_locked' => false,
        ];
    }

    /**
     * Only staff may start threads in a locked category.
     */
    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}
