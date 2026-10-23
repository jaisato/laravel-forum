<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationActionType;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationAction>
 */
class ModerationActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'moderator_id' => User::factory()->moderator(),
            'action' => ModerationActionType::PostHidden,
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'reason' => fake()->optional()->sentence(),
        ];
    }

    /**
     * An action taken by the system itself (e.g. the AI moderator).
     */
    public function system(): static
    {
        return $this->state(['moderator_id' => null]);
    }
}
