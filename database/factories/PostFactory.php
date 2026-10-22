<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
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

    /**
     * Keep body_html consistent with whatever body a test passes in.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Post $post): void {
            if (blank($post->body_html)) {
                $post->body_html = collect(preg_split("/\n{2,}/", $post->body) ?: [])
                    ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
                    ->implode("\n");
            }
        });
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
