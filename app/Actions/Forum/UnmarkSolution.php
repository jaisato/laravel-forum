<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\Reputation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Removes the solution mark and the bonus it earned. Doing it twice
 * changes nothing.
 */
final class UnmarkSolution
{
    public function __construct(private readonly Reputation $reputation) {}

    public function handle(Thread $thread, User $actor): void
    {
        DB::transaction(function () use ($thread, $actor): void {
            $thread = Thread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            // The request was authorised on the copy it loaded: a moderator
            // may have locked or hidden the thread since.
            if (! $thread->mayBeChangedBy($actor->current())) {
                throw new AuthorizationException(__('This thread can no longer be changed.'));
            }

            if ($thread->solution_post_id === null) {
                return;
            }

            $solution = Post::withTrashed()->find($thread->solution_post_id);

            if ($solution !== null) {
                $this->reputation->solution($thread, $solution, -1);
            }

            $thread->forceFill(['solution_post_id' => null])->save();
        });
    }
}
