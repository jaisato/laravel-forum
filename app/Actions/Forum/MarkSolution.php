<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Events\SolutionMarked;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\Reputation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Marks the reply that solves a thread. Choosing another reply moves the
 * reputation bonus to its author; choosing the same one again changes
 * nothing.
 */
final class MarkSolution
{
    public function __construct(private readonly Reputation $reputation) {}

    public function handle(Thread $thread, Post $post, User $actor): void
    {
        if ((int) $post->thread_id !== $thread->id || (int) $thread->first_post_id === $post->id) {
            throw new InvalidArgumentException('Only a reply of the thread can be its solution.');
        }

        DB::transaction(function () use ($thread, $post, $actor): void {
            $thread = Thread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if ((int) $thread->solution_post_id === $post->id) {
                return;
            }

            if ($thread->solution_post_id !== null) {
                $previous = Post::withTrashed()->find($thread->solution_post_id);

                if ($previous !== null) {
                    $this->reputation->solution($thread, $previous, -1);
                }
            }

            $thread->forceFill(['solution_post_id' => $post->id])->save();
            $this->reputation->solution($thread, $post, 1);

            SolutionMarked::dispatch($thread, $post, $actor);
        });
    }
}
