<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use App\Models\Post;
use App\Models\User;

/**
 * Post permissions (spec §5). Reactions and reports only go to published
 * posts written by someone else; reports stay possible in locked threads.
 */
class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($user?->isStaff()) {
            return true;
        }

        // The thread relation leaves deleted threads out: their posts go too.
        return $post->isVisibleTo($user) && $post->thread?->isVisibleTo($user) === true;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->canModerate() || $this->authorMayChange($user, $post);
    }

    /**
     * Deleting the opening post deletes the thread, so the thread rule
     * applies to it.
     */
    public function delete(User $user, Post $post): bool
    {
        if ($user->canModerate()) {
            return true;
        }

        if ($post->isFirst()) {
            return $post->thread !== null && $user->can('delete', $post->thread);
        }

        return $this->authorMayChange($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->canModerate();
    }

    public function hide(User $user, Post $post): bool
    {
        return $user->canModerate();
    }

    public function approve(User $user, Post $post): bool
    {
        return $user->canModerate();
    }

    public function react(User $user, Post $post): bool
    {
        return $this->mayEngageWith($user, $post) && $post->thread?->is_locked === false;
    }

    public function report(User $user, Post $post): bool
    {
        return $this->mayEngageWith($user, $post);
    }

    private function authorMayChange(User $user, Post $post): bool
    {
        return $user->canParticipate()
            && $post->isAuthoredBy($user)
            && $post->status !== PostStatus::Hidden
            && ! $post->trashed()
            && $post->thread?->isVisibleTo($user) === true
            && ! $post->thread->is_locked;
    }

    private function mayEngageWith(User $user, Post $post): bool
    {
        return $user->canParticipate()
            && ! $post->isAuthoredBy($user)
            && $post->status === PostStatus::Published
            && ! $post->trashed()
            && $post->thread?->status === ThreadStatus::Published;
    }
}
