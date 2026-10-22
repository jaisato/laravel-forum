<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ThreadStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
class ThreadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(fake()->numberBetween(4, 9)), '.'),
            'status' => ThreadStatus::Published,
            'is_pinned' => false,
            'is_locked' => false,
            'replies_count' => 0,
            'views_count' => 0,
        ];
    }

    /**
     * A thread never exists without its opening post, which is its body.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Thread $thread): void {
            if ($thread->first_post_id !== null) {
                return;
            }

            $post = Post::factory()->create([
                'thread_id' => $thread->id,
                'user_id' => $thread->user_id,
                'status' => $thread->status->value,
                'created_at' => $thread->created_at,
                'updated_at' => $thread->created_at,
            ]);

            $thread->forceFill([
                'first_post_id' => $post->id,
                'last_post_id' => $post->id,
                'last_posted_at' => $post->created_at,
            ])->saveQuietly();
        });
    }

    public function pending(): static
    {
        return $this->state(['status' => ThreadStatus::PendingReview]);
    }

    public function hidden(): static
    {
        return $this->state(['status' => ThreadStatus::Hidden]);
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}
