<?php

declare(strict_types=1);

namespace App\Policies;

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

    /**
     * Whether the user may open the new thread form: some category takes
     * their threads.
     */
    public function createAny(User $user): bool
    {
        return Category::query()->get()->contains(fn (Category $category): bool => $category->acceptsThreadsFrom($user));
    }

    public function create(User $user, Category $category): bool
    {
        return $category->acceptsThreadsFrom($user);
    }

    public function update(User $user, Thread $thread): bool
    {
        return $thread->mayBeChangedBy($user);
    }

    /**
     * Authors may delete their thread only while nobody has replied.
     */
    public function delete(User $user, Thread $thread): bool
    {
        return $thread->mayBeDeletedBy($user);
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
        return $thread->acceptsRepliesFrom($user);
    }

    public function markSolution(User $user, Thread $thread): bool
    {
        return $thread->mayBeChangedBy($user);
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
        return $user->canParticipate() && $thread->canHaveSummary();
    }
}
