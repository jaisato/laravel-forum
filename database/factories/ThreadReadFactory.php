<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadRead>
 */
class ThreadReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => User::factory(),
            'last_read_post_id' => 0,
            'last_read_at' => now(),
        ];
    }
}
