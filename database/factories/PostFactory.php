<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Database\Factories\Concerns\FillsBodyHtml;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    use FillsBodyHtml;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraphs(2, true),
            'status' => PostStatus::Published,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => PostStatus::PendingReview]);
    }

    public function hidden(): static
    {
        return $this->state(['status' => PostStatus::Hidden]);
    }
}
