<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Thread;
use App\Models\ThreadSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreadSubscription>
 */
class ThreadSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => User::factory(),
        ];
    }
}
