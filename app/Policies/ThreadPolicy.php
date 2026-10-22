<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ThreadStatus;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Thread permissions (spec §5). A locked thread is read-only for everyone
 * but staff; replies and new threads in a locked category are for staff.
 */
class ThreadPolicy
{
    /**
     * Threads the viewer may not see answer 404, so their existence does not
     * leak (Review Focus 2).
     */
    public function view(?User $user, Thread $thread): Response
    {
        return $thread->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user, Category $category): bool
    {
        return $user->canModerate()
            || ($user->canParticipate() && ! $category->is_locked);
    }

    public function update(User $user, Thread $thread): bool
    {
        return $user->canModerate() || $this->authorMayChange($user, $thread);
    }

    /**
     * Authors may delete their thread only while nobody has replied.
     */
    public function delete(User $user, Thread $thread): bool
    {
        return $user->canModerate()
            || ($this->authorMayChange($user, $thread) && $thread->replies_count === 0);
    }

    public function restore(User $user, Thread $thread): bool
    {
        return $user->canModerate();
    }

    public function pin(User $user, Thread $thread): bool
    {
        return $user->canModerate();
    }

    public function lock(User $user, Thread $thread): bool
    {
        return $user->canModerate();
    }

    public function move(User $user, Thread $thread): bool
    {
        return $user->canModerate();
    }

    public function reply(User $user, Thread $thread): bool
    {
        if ($user->canModerate()) {
            return true;
        }

        return $user->canParticipate()
            && $thread->status === ThreadStatus::Published
            && ! $thread->trashed()
            && ! $thread->is_locked
            && ! $thread->category->is_locked;
    }

    public function markSolution(User $user, Thread $thread): bool
    {
        return $user->canModerate() || $this->authorMayChange($user, $thread);
    }

    public function subscribe(User $user, Thread $thread): bool
    {
        return $user->canParticipate() && $thread->isVisibleTo($user);
    }

    /**
     * Asking for an AI summary; whether the feature is enabled at all is
     * decided by the controller (404 when it is off).
     */
    public function summarize(User $user, Thread $thread): bool
    {
        return $user->canParticipate()
            && $thread->isVisibleTo($user)
            && $thread->replies_count >= (int) config('forum.ai.summaries.min_replies');
    }

    private function authorMayChange(User $user, Thread $thread): bool
    {
        return $user->canParticipate()
            && $thread->isAuthoredBy($user)
            && $thread->status !== ThreadStatus::Hidden
            && ! $thread->trashed()
            && ! $thread->is_locked;
    }
}
