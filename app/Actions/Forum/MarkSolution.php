<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Enums\PostStatus;
use App\Events\SolutionMarked;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\Reputation;
use Illuminate\Auth\Access\AuthorizationException;
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
            // The request was authorised on the copy it loaded: a moderator
            // may have locked or hidden the thread since.
            if (! $thread->mayBeChangedBy($actor->current())) {
                throw new AuthorizationException(__('This thread can no longer be changed.'));
            }

            // Read again under lock: the answer may have been hidden meanwhile.
            $post = Post::query()->whereKey($post->id)->lockForUpdate()->first();

            if ($post === null || $post->status !== PostStatus::Published) {
                throw new InvalidArgumentException('Only a reply everybody can see can be the solution.');
            }

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
